<?php

namespace App\Services\Notification\Channels;

use App\Models\Notification;

interface ChannelInterface
{
    /**
     * 发送通知并返回结果
     *
     * @return array{code: int, msg?: string} 返回格式：['code' => 1, 'msg' => '可选消息'] 成功，['code' => 0, 'msg' => '错误消息'] 失败
     */
    public function send(Notification $notification): array;

    /**
     * 检查通道是否可用
     */
    public function isAvailable(): bool;
}
