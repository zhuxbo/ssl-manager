<?php

use App\Services\Notification\ChannelManager;
use App\Services\Notification\Channels\ChannelInterface;
use App\Services\Notification\Channels\MailChannel;
use App\Services\Notification\Channels\SmsChannel;
use Mockery;

afterEach(function () {
    Mockery::close();
});

test('获取已注册的 mail 通道', function () {
    $mailChannel = Mockery::mock(MailChannel::class);
    $smsChannel = Mockery::mock(SmsChannel::class);

    $manager = new ChannelManager($mailChannel, $smsChannel);

    $channel = $manager->channel('mail');

    expect($channel)->toBe($mailChannel);
    expect($channel)->toBeInstanceOf(ChannelInterface::class);
});

test('获取已注册的 sms 通道', function () {
    $mailChannel = Mockery::mock(MailChannel::class);
    $smsChannel = Mockery::mock(SmsChannel::class);

    $manager = new ChannelManager($mailChannel, $smsChannel);

    $channel = $manager->channel('sms');

    expect($channel)->toBe($smsChannel);
    expect($channel)->toBeInstanceOf(ChannelInterface::class);
});

test('获取不存在的通道时抛出异常', function () {
    $mailChannel = Mockery::mock(MailChannel::class);
    $smsChannel = Mockery::mock(SmsChannel::class);

    $manager = new ChannelManager($mailChannel, $smsChannel);

    $manager->channel('wechat');
})->throws(InvalidArgumentException::class, '未知的通知通道: wechat');
