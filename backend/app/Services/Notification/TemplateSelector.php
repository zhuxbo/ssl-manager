<?php

namespace App\Services\Notification;

use App\Models\NotificationTemplate;

class TemplateSelector
{
    public function select(string $code, ?array $preferredChannels = null): TemplateSelection
    {
        $templates = NotificationTemplate::query()
            ->where('code', $code)
            ->where('status', 1)
            ->get();

        $channelTemplates = [];
        $preferredChannels = $preferredChannels ? array_values(array_unique($preferredChannels)) : null;

        foreach ($templates as $template) {
            $channels = $template->channels ?? [];
            foreach ($channels as $channel) {
                if ($preferredChannels && ! in_array($channel, $preferredChannels, true)) {
                    continue;
                }

                if (! isset($channelTemplates[$channel])) {
                    $channelTemplates[$channel] = $template;
                }
            }
        }

        return new TemplateSelection($channelTemplates);
    }
}
