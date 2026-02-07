<?php

namespace App\Services\Notification;

use App\Jobs\NotificationJob;
use App\Services\Notification\DTOs\NotificationIntent;
use App\Services\Notification\Guards\ChannelGuardManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class NotificationCenter
{
    public function __construct(
        protected TemplateSelector $templateSelector,
        protected ChannelGuardManager $guardManager
    ) {}

    public function dispatch(NotificationIntent $intent): void
    {
        $notifiable = $this->resolveNotifiable($intent);
        if (! $notifiable) {
            $this->logSkip($intent, '通知接收者不存在');

            return;
        }

        $selection = $this->templateSelector->select($intent->code, $intent->preferredChannels);
        if ($selection->isEmpty()) {
            $this->logSkip($intent, '通知模板不存在或未启用');

            return;
        }

        $channelTemplates = $selection->channelTemplates();

        $guardResult = $this->guardManager->filter($notifiable, $intent, $channelTemplates);
        $allowedChannels = $guardResult['allowed'] ?? [];

        if (empty($allowedChannels)) {
            $this->logSkip($intent, '所有通道被 Guard 拦截', $guardResult['rejected'] ?? []);

            return;
        }

        foreach ($selection->groupByTemplate($allowedChannels) as $group) {
            $template = $group['template'];
            $channelsForTemplate = $group['channels'];

            // 清理和去重通道列表
            $sanitizedChannels = array_values(array_unique($channelsForTemplate));

            // 为每个通道单独派发 Job，使用对应的 builder
            foreach ($sanitizedChannels as $channel) {
                try {
                    $builderClass = $this->resolveBuilder($intent->code, $channel);
                } catch (RuntimeException) {
                    $this->logSkip($intent, "通知构建器未配置: $intent->code.$channel");

                    continue;
                }

                NotificationJob::dispatch(
                    $intent->notifiableType,
                    $intent->notifiableId,
                    $template->id,
                    $channel,
                    $intent->context,
                    $builderClass
                )->onQueue(Config::get('queue.names.notifications'));
            }
        }
    }

    protected function resolveBuilder(string $code, string $channel): string
    {
        $map = Config::get('notification.builders', []);
        $key = "$code.$channel";

        // 检查是否存在明确配置
        if (array_key_exists($key, $map)) {
            $class = $map[$key];
            // 空字符串表示明确禁用该组合
            if (empty($class)) {
                throw new RuntimeException("通知构建器已禁用: $key");
            }

            return $class;
        }

        // 回退到默认 builder
        $class = Config::get('notification.default_builder');
        if (empty($class)) {
            throw new RuntimeException("未配置通知构建器: $key");
        }

        return $class;
    }

    protected function resolveNotifiable(NotificationIntent $intent): ?Model
    {
        $map = Config::get('notification.notifiables', []);
        $class = $map[$intent->notifiableType] ?? $intent->notifiableType;

        if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            return null;
        }

        return $class::find($intent->notifiableId);
    }

    protected function logSkip(NotificationIntent $intent, string $reason, array $meta = []): void
    {
        if (! config('app.debug')) {
            return;
        }

        Log::debug('[notification.skip] '.$reason, array_merge([
            'code' => $intent->code,
            'notifiable_type' => $intent->notifiableType,
            'notifiable_id' => $intent->notifiableId,
        ], $meta));
    }
}
