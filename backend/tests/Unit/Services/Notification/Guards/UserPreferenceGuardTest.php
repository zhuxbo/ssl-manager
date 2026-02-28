<?php

use App\Models\NotificationTemplate;
use App\Services\Notification\DTOs\NotificationIntent;
use App\Services\Notification\Guards\UserPreferenceGuard;
use Illuminate\Database\Eloquent\Model;
use Mockery;

afterEach(function () {
    Mockery::close();
});

test('用户开启通知时允许', function () {
    $notifiable = new class extends Model
    {
        public function allowsNotificationChannel(string $channel, string $type): bool
        {
            return true;
        }
    };

    $template = Mockery::mock(NotificationTemplate::class);
    $template->shouldReceive('getAttribute')->with('code')->andReturn('cert_expire');
    $intent = new NotificationIntent('cert_expire', 'user', 1);

    $guard = new UserPreferenceGuard;
    $result = $guard->allow($notifiable, 'mail', $template, $intent);

    expect($result)->toBeTrue();
    expect($guard->reason())->toBeNull();
});

test('用户关闭通知时拒绝', function () {
    $notifiable = new class extends Model
    {
        public function allowsNotificationChannel(string $channel, string $type): bool
        {
            return false;
        }
    };

    $template = Mockery::mock(NotificationTemplate::class);
    $template->shouldReceive('getAttribute')->with('code')->andReturn('cert_expire');
    $intent = new NotificationIntent('cert_expire', 'user', 1);

    $guard = new UserPreferenceGuard;
    $result = $guard->allow($notifiable, 'mail', $template, $intent);

    expect($result)->toBeFalse();
    expect($guard->reason())->toBe('用户关闭了该通道');
});

test('notifiable 不支持偏好检查时默认允许', function () {
    // 使用一个没有 allowsNotificationChannel 方法的 Model
    $notifiable = new class extends Model {};

    $template = Mockery::mock(NotificationTemplate::class);
    $template->shouldReceive('getAttribute')->with('code')->andReturn('cert_expire');
    $intent = new NotificationIntent('cert_expire', 'user', 1);

    $guard = new UserPreferenceGuard;
    $result = $guard->allow($notifiable, 'mail', $template, $intent);

    expect($result)->toBeTrue();
});

test('模板代码有 _html 后缀时正确解析偏好类型', function () {
    $notifiable = new class extends Model
    {
        public function allowsNotificationChannel(string $channel, string $type): bool
        {
            return true;
        }
    };

    $template = Mockery::mock(NotificationTemplate::class);
    $template->shouldReceive('getAttribute')->with('code')->andReturn('cert_expire_html');
    $intent = new NotificationIntent('cert_expire_html', 'user', 1);

    $guard = new UserPreferenceGuard;
    $result = $guard->allow($notifiable, 'mail', $template, $intent);

    expect($result)->toBeTrue();
});

test('模板代码有 _text 后缀时正确解析偏好类型', function () {
    $notifiable = new class extends Model
    {
        public function allowsNotificationChannel(string $channel, string $type): bool
        {
            return false;
        }
    };

    $template = Mockery::mock(NotificationTemplate::class);
    $template->shouldReceive('getAttribute')->with('code')->andReturn('order_complete_text');
    $intent = new NotificationIntent('order_complete_text', 'user', 1);

    $guard = new UserPreferenceGuard;
    $result = $guard->allow($notifiable, 'sms', $template, $intent);

    expect($result)->toBeFalse();
    expect($guard->reason())->toBe('用户关闭了该通道');
});
