<?php

use App\Models\NotificationTemplate;
use App\Services\Notification\DTOs\NotificationIntent;
use App\Services\Notification\Guards\ContactChannelGuard;
use Illuminate\Database\Eloquent\Model;

afterEach(function () {
    Mockery::close();
});

test('有邮箱时允许 mail 通道', function () {
    $notifiable = Mockery::mock(Model::class);
    $notifiable->shouldReceive('getAttribute')->with('email')->andReturn('test@example.com');
    $template = Mockery::mock(NotificationTemplate::class);
    $intent = new NotificationIntent('test_code', 'user', 1, ['email' => 'test@example.com']);

    $guard = new ContactChannelGuard;
    $result = $guard->allow($notifiable, 'mail', $template, $intent);

    expect($result)->toBeTrue();
    expect($guard->reason())->toBeNull();
});

test('context 中有邮箱时允许 mail 通道', function () {
    $notifiable = Mockery::mock(Model::class);
    $template = Mockery::mock(NotificationTemplate::class);
    $intent = new NotificationIntent('test_code', 'user', 1, ['email' => 'context@example.com']);

    $guard = new ContactChannelGuard;
    $result = $guard->allow($notifiable, 'mail', $template, $intent);

    expect($result)->toBeTrue();
    expect($guard->reason())->toBeNull();
});

test('无邮箱时拒绝 mail 通道', function () {
    $notifiable = Mockery::mock(Model::class)->shouldIgnoreMissing();
    $notifiable->shouldReceive('getAttribute')->with('email')->andReturn(null);
    $template = Mockery::mock(NotificationTemplate::class);
    $intent = new NotificationIntent('test_code', 'user', 1);

    $guard = new ContactChannelGuard;
    $result = $guard->allow($notifiable, 'mail', $template, $intent);

    expect($result)->toBeFalse();
    expect($guard->reason())->toBe('收件人邮箱为空');
});

test('有手机号时允许 sms 通道', function () {
    $notifiable = Mockery::mock(Model::class);
    $notifiable->shouldReceive('getAttribute')->with('mobile')->andReturn('13800138000');
    $template = Mockery::mock(NotificationTemplate::class);
    $intent = new NotificationIntent('test_code', 'user', 1, ['mobile' => '13800138000']);

    $guard = new ContactChannelGuard;
    $result = $guard->allow($notifiable, 'sms', $template, $intent);

    expect($result)->toBeTrue();
    expect($guard->reason())->toBeNull();
});

test('无手机号时拒绝 sms 通道', function () {
    $notifiable = Mockery::mock(Model::class)->shouldIgnoreMissing();
    $notifiable->shouldReceive('getAttribute')->with('mobile')->andReturn(null);
    $template = Mockery::mock(NotificationTemplate::class);
    $intent = new NotificationIntent('test_code', 'user', 1);

    $guard = new ContactChannelGuard;
    $result = $guard->allow($notifiable, 'sms', $template, $intent);

    expect($result)->toBeFalse();
    expect($guard->reason())->toBe('收件人手机号为空');
});

test('其他通道类型直接允许', function () {
    $notifiable = Mockery::mock(Model::class);
    $template = Mockery::mock(NotificationTemplate::class);
    $intent = new NotificationIntent('test_code', 'user', 1);

    $guard = new ContactChannelGuard;
    $result = $guard->allow($notifiable, 'webhook', $template, $intent);

    expect($result)->toBeTrue();
});
