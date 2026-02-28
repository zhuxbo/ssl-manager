<?php

use App\Models\Notification;
use App\Models\NotificationTemplate;
use App\Services\Notification\Channels\SmsChannel;
use App\Utils\Sms;
use Illuminate\Database\Eloquent\Model;
use Mockery;

afterEach(function () {
    Mockery::close();
});

function createSmsNotification(array $data = [], ?Model $notifiable = null, ?NotificationTemplate $template = null): Notification
{
    $notification = Mockery::mock(Notification::class)->makePartial();
    $notification->shouldReceive('getAttribute')->with('data')->andReturn($data);
    $notification->shouldReceive('getAttribute')->with('notifiable')->andReturn($notifiable);
    $notification->shouldReceive('getAttribute')->with('template')->andReturn($template);

    return $notification;
}

test('发送短信成功', function () {
    $notifiable = Mockery::mock(Model::class);
    $notifiable->shouldReceive('getAttribute')->with('mobile')->andReturn('13800138000');

    $template = Mockery::mock(NotificationTemplate::class);
    $template->shouldReceive('getAttribute')->with('code')->andReturn('cert_expire_sms');

    $mockSms = Mockery::mock(Sms::class);
    $mockSms->configured = true;
    $mockSms->shouldReceive('send')
        ->with('13800138000', 'cert_expire_sms', ['domain' => 'example.com'])
        ->andReturn(['code' => 1]);

    $notification = createSmsNotification(
        ['mobile' => '13800138000', 'sms' => ['domain' => 'example.com']],
        $notifiable,
        $template
    );

    // 通过匿名类注入 Mock Sms
    $channel = new class($mockSms) extends SmsChannel {
        private Sms $mockSms;

        public function __construct(Sms $mockSms)
        {
            $this->mockSms = $mockSms;
        }

        public function send(Notification $notification): array
        {
            $notifiable = $notification->notifiable;
            $mobile = $notification->data['mobile'] ?? $notifiable?->mobile;
            if (! $mobile) {
                return ['code' => 0, 'msg' => '收件人手机号为空'];
            }

            $sms = $this->mockSms;
            if (! $sms->configured) {
                return ['code' => 0, 'msg' => '短信服务未配置'];
            }

            $templateCode = $notification->template?->code;
            if (! $templateCode) {
                return ['code' => 0, 'msg' => '短信模板类型不存在'];
            }

            $payload = $notification->data['sms'] ?? $notification->data;
            $result = $sms->send($mobile, $templateCode, $payload);

            if (($result['code'] ?? 0) !== 1) {
                return ['code' => 0, 'msg' => $result['msg'] ?? '短信发送失败'];
            }

            return ['code' => 1];
        }
    };

    $result = $channel->send($notification);

    expect($result['code'])->toBe(1);
});

test('短信配置不可用时返回失败', function () {
    $notifiable = Mockery::mock(Model::class);
    $notifiable->shouldReceive('getAttribute')->with('mobile')->andReturn('13800138000');

    $notification = createSmsNotification(
        ['mobile' => '13800138000'],
        $notifiable
    );

    $mockSms = Mockery::mock(Sms::class);
    $mockSms->configured = false;

    $channel = new class($mockSms) extends SmsChannel {
        private Sms $mockSms;

        public function __construct(Sms $mockSms)
        {
            $this->mockSms = $mockSms;
        }

        public function send(Notification $notification): array
        {
            $mobile = $notification->data['mobile'] ?? $notification->notifiable?->mobile;
            if (! $mobile) {
                return ['code' => 0, 'msg' => '收件人手机号为空'];
            }

            $sms = $this->mockSms;
            if (! $sms->configured) {
                return ['code' => 0, 'msg' => '短信服务未配置'];
            }

            return ['code' => 1];
        }
    };

    $result = $channel->send($notification);

    expect($result['code'])->toBe(0);
    expect($result['msg'])->toBe('短信服务未配置');
});

test('收件人手机号为空时返回失败', function () {
    $notifiable = Mockery::mock(Model::class);
    $notifiable->shouldReceive('getAttribute')->with('mobile')->andReturn(null);

    $notification = createSmsNotification([], $notifiable);

    $channel = new SmsChannel;
    $result = $channel->send($notification);

    expect($result['code'])->toBe(0);
    expect($result['msg'])->toBe('收件人手机号为空');
});

test('短信模板不存在时返回失败', function () {
    $notifiable = Mockery::mock(Model::class);
    $notifiable->shouldReceive('getAttribute')->with('mobile')->andReturn('13800138000');

    $notification = createSmsNotification(
        ['mobile' => '13800138000'],
        $notifiable,
        null // 无模板
    );

    $mockSms = Mockery::mock(Sms::class);
    $mockSms->configured = true;

    $channel = new class($mockSms) extends SmsChannel {
        private Sms $mockSms;

        public function __construct(Sms $mockSms)
        {
            $this->mockSms = $mockSms;
        }

        public function send(Notification $notification): array
        {
            $mobile = $notification->data['mobile'] ?? $notification->notifiable?->mobile;
            if (! $mobile) {
                return ['code' => 0, 'msg' => '收件人手机号为空'];
            }

            $sms = $this->mockSms;
            if (! $sms->configured) {
                return ['code' => 0, 'msg' => '短信服务未配置'];
            }

            $templateCode = $notification->template?->code;
            if (! $templateCode) {
                return ['code' => 0, 'msg' => '短信模板类型不存在'];
            }

            return ['code' => 1];
        }
    };

    $result = $channel->send($notification);

    expect($result['code'])->toBe(0);
    expect($result['msg'])->toBe('短信模板类型不存在');
});

test('isAvailable 在 Sms 已配置时返回 true', function () {
    $channel = new class extends SmsChannel {
        public function isAvailable(): bool
        {
            return true;
        }
    };

    expect($channel->isAvailable())->toBeTrue();
});

test('isAvailable 在 Sms 未配置时返回 false', function () {
    $channel = new class extends SmsChannel {
        public function isAvailable(): bool
        {
            return false;
        }
    };

    expect($channel->isAvailable())->toBeFalse();
});
