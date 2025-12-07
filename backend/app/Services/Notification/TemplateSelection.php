<?php

namespace App\Services\Notification;

use App\Models\NotificationTemplate;

class TemplateSelection
{
    public function __construct(protected array $channelTemplates = []) {}

    public function isEmpty(): bool
    {
        return empty($this->channelTemplates);
    }

    public function channels(): array
    {
        return array_keys($this->channelTemplates);
    }

    public function channelTemplates(): array
    {
        return $this->channelTemplates;
    }

    /**
     * 根据模板分组通道
     *
     * @param  array<string>  $channels
     * @return array<int, array{template: NotificationTemplate, channels: array<string>}>
     */
    public function groupByTemplate(array $channels): array
    {
        $grouped = [];

        foreach ($channels as $channel) {
            if (! isset($this->channelTemplates[$channel])) {
                continue;
            }

            $template = $this->channelTemplates[$channel];
            $templateId = $template->id;
            if (! isset($grouped[$templateId])) {
                $grouped[$templateId] = [
                    'template' => $template,
                    'channels' => [],
                ];
            }

            $grouped[$templateId]['channels'][] = $channel;
        }

        return array_values($grouped);
    }
}
