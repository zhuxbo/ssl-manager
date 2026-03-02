<?php

use App\Models\NotificationTemplate;
use App\Services\Notification\ChannelManager;
use App\Services\Notification\Channels\ChannelInterface;
use App\Services\Notification\DTOs\NotificationIntent;
use App\Services\Notification\Guards\SystemChannelGuard;
use Illuminate\Database\Eloquent\Model;

afterEach(function () {
    Mockery::close();
});

test('系统配置允许时通过', function () {
    $channelDriver = Mockery::mock(ChannelInterface::class);
    $channelDriver->shouldReceive('isAvailable')->andReturn(true);

    $channelManager = Mockery::mock(ChannelManager::class);
    $channelManager->shouldReceive('channel')->with('mail')->andReturn($channelDriver);

    $notifiable = Mockery::mock(Model::class);
    $template = Mockery::mock(NotificationTemplate::class);
    $intent = new NotificationIntent('test_code', 'user', 1);

    $guard = new SystemChannelGuard($channelManager);
    $result = $guard->allow($notifiable, 'mail', $template, $intent);

    expect($result)->toBeTrue();
    expect($guard->reason())->toBeNull();
});

test('系统配置禁止时拒绝（通道不可用）', function () {
    $channelDriver = Mockery::mock(ChannelInterface::class);
    $channelDriver->shouldReceive('isAvailable')->andReturn(false);

    $channelManager = Mockery::mock(ChannelManager::class);
    $channelManager->shouldReceive('channel')->with('mail')->andReturn($channelDriver);

    $notifiable = Mockery::mock(Model::class);
    $template = Mockery::mock(NotificationTemplate::class);
    $intent = new NotificationIntent('test_code', 'user', 1);

    $guard = new SystemChannelGuard($channelManager);
    $result = $guard->allow($notifiable, 'mail', $template, $intent);

    expect($result)->toBeFalse();
    expect($guard->reason())->toBe('通道未配置');
});

test('通道未实现时拒绝', function () {
    $channelManager = Mockery::mock(ChannelManager::class);
    $channelManager->shouldReceive('channel')
        ->with('wechat')
        ->andThrow(new InvalidArgumentException('未知的通知通道: wechat'));

    $notifiable = Mockery::mock(Model::class);
    $template = Mockery::mock(NotificationTemplate::class);
    $intent = new NotificationIntent('test_code', 'user', 1);

    $guard = new SystemChannelGuard($channelManager);
    $result = $guard->allow($notifiable, 'wechat', $template, $intent);

    expect($result)->toBeFalse();
    expect($guard->reason())->toBe('通道未实现');
});
