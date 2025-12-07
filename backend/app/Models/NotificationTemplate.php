<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Blade;
use Illuminate\Validation\ValidationException;

class NotificationTemplate extends BaseModel
{
    protected $fillable = [
        'name',
        'code',
        'content',
        'variables',
        'example',
        'status',
        'channels',
    ];

    protected $casts = [
        'variables' => 'json',
        'status' => 'integer',
        'channels' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (NotificationTemplate $template) {
            $template->channels = $template->normalizeChannels($template->channels ?? []);

            if (empty($template->channels)) {
                throw ValidationException::withMessages([
                    'channels' => ['通知模板至少需要启用一个通道'],
                ]);
            }

            foreach ($template->channels as $channel) {
                $conflict = self::query()
                    ->where('code', $template->code)
                    ->when($template->exists, fn ($query) => $query->where('id', '!=', $template->getKey()))
                    ->whereJsonContains('channels', $channel)
                    ->exists();

                if ($conflict) {
                    throw ValidationException::withMessages([
                        'channels' => ["$template->code 已绑定 $channel 通道，请勿重复配置"],
                    ]);
                }
            }
        });
    }

    /**
     * 通知
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'template_id');
    }

    /**
     * 渲染模板内容
     *
     * 使用 Blade 模板引擎渲染，支持完整的 Blade 语法
     */
    public function render(array $data = []): string
    {
        $content = (string) ($this->content ?? '');

        return Blade::render($content, $data);
    }

    protected function content(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? base64_decode($value) : null,
            set: fn ($value) => $this->encodeContent($value)
        );
    }

    protected function example(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? base64_decode($value) : null,
            set: fn ($value) => $this->encodeContent($value)
        );
    }

    protected function encodeContent(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = $value;
        if ($trimmed === '') {
            return null;
        }

        return base64_encode($trimmed);
    }

    protected function normalizeChannels(mixed $channels): array
    {
        if (is_string($channels)) {
            $channels = json_decode($channels, true) ?? [$channels];
        }

        if (! is_array($channels)) {
            $channels = [];
        }

        $channels = array_map(fn ($channel) => is_string($channel) ? trim($channel) : null, $channels);
        $channels = array_filter($channels, fn ($channel) => ! empty($channel));

        return array_values(array_unique($channels));
    }
}
