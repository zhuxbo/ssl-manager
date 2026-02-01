<?php

namespace Tests\Feature;

use App\Jobs\NotificationJob;
use App\Models\Notification;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Services\Notification\Builders\DefaultNotificationBuilder;
use App\Services\Notification\ChannelManager;
use App\Services\Notification\Channels\MailChannel;
use App\Services\Notification\NotificationRepository;
use Database\Seeders\DatabaseSeeder;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('database')]
class NotificationJobTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = DatabaseSeeder::class;

    protected function createUser(array $overrides = []): User
    {
        $email = $overrides['email'] ?? uniqid().'@example.com';
        unset($overrides['email']);

        return User::firstOrCreate(
            ['email' => $email],
            array_merge([
                'username' => $overrides['username'] ?? 'user_'.uniqid(),
                'password' => 'secret',
                'join_at' => now(),
            ], $overrides)
        );
    }

    protected function createTemplate(array $overrides = []): NotificationTemplate
    {
        return NotificationTemplate::create(array_merge([
            'code' => 'test_'.uniqid(),
            'name' => 'Test Template',
            'content' => 'Hello {{ $username }}',
            'variables' => ['username'],
            'status' => 1,
            'channels' => ['mail'],
        ], $overrides));
    }

    public function test_handles_notification_successfully(): void
    {
        $user = $this->createUser(['mobile' => '138'.uniqid()]);
        $template = $this->createTemplate();

        // Mock MailChannel to return success
        $this->mock(MailChannel::class, function ($mock) {
            $mock->shouldReceive('send')
                ->once()
                ->andReturn(['code' => 1, 'msg' => '发送成功']);
        });

        $job = new NotificationJob(
            'user',
            $user->id,
            $template->id,
            'mail',
            ['username' => $user->username],
            DefaultNotificationBuilder::class
        );

        $job->handle(app(NotificationRepository::class), app(ChannelManager::class));

        // 验证通知已创建并标记为已发送
        $notification = Notification::where('notifiable_id', $user->id)->first();
        $this->assertNotNull($notification);
        $this->assertEquals(Notification::STATUS_SENT, $notification->status);
        $this->assertNotNull($notification->sent_at);

        // 验证发送结果
        $this->assertArrayHasKey('result', $notification->data);
        $this->assertEquals('mail', $notification->data['result']['channel']);
        $this->assertEquals(Notification::STATUS_SENT, $notification->data['result']['status']);
    }

    public function test_skips_when_template_not_found(): void
    {
        $user = $this->createUser();

        $job = new NotificationJob(
            'user',
            $user->id,
            99999, // 不存在的模板ID
            'mail',
            [],
            DefaultNotificationBuilder::class
        );

        $job->handle(app(NotificationRepository::class), app(ChannelManager::class));

        // 验证没有为这个模板创建通知
        $this->assertDatabaseMissing('notifications', [
            'template_id' => 99999,
        ]);
    }

    public function test_skips_when_template_disabled(): void
    {
        $user = $this->createUser();
        $template = $this->createTemplate(['status' => 0]); // 已禁用

        $job = new NotificationJob(
            'user',
            $user->id,
            $template->id,
            'mail',
            ['username' => $user->username],
            DefaultNotificationBuilder::class
        );

        $job->handle(app(NotificationRepository::class), app(ChannelManager::class));

        // 验证没有为这个禁用的模板创建通知
        $this->assertDatabaseMissing('notifications', [
            'template_id' => $template->id,
        ]);
    }

    public function test_skips_when_notifiable_not_found(): void
    {
        $template = $this->createTemplate();

        $job = new NotificationJob(
            'user',
            99999, // 不存在的用户ID
            $template->id,
            'mail',
            [],
            DefaultNotificationBuilder::class
        );

        $job->handle(app(NotificationRepository::class), app(ChannelManager::class));

        // 验证没有为这个不存在的用户创建通知（通过模板ID和不存在的用户ID组合判断）
        $notification = Notification::where('template_id', $template->id)
            ->where('notifiable_type', 'user')
            ->where('notifiable_id', 99999)
            ->first();
        $this->assertNull($notification);
    }

    public function test_marks_as_failed_when_all_channels_fail(): void
    {
        $user = $this->createUser();
        $template = $this->createTemplate();

        // Mock MailChannel to return failure
        $this->mock(MailChannel::class, function ($mock) {
            $mock->shouldReceive('send')
                ->once()
                ->andReturn(['code' => 0, 'msg' => '发送失败']);
        });

        $job = new NotificationJob(
            'user',
            $user->id,
            $template->id,
            'mail',
            ['username' => $user->username],
            DefaultNotificationBuilder::class
        );

        $job->handle(app(NotificationRepository::class), app(ChannelManager::class));

        // 验证通知标记为失败
        $notification = Notification::where('notifiable_id', $user->id)->first();
        $this->assertNotNull($notification);
        $this->assertEquals(Notification::STATUS_FAILED, $notification->status);
        $this->assertNull($notification->sent_at);

        // 验证发送结果
        $this->assertEquals('mail', $notification->data['result']['channel']);
        $this->assertEquals(Notification::STATUS_FAILED, $notification->data['result']['status']);
        $this->assertEquals('发送失败', $notification->data['result']['message']);
    }

    public function test_skips_when_channel_not_supported_by_template(): void
    {
        $user = $this->createUser();
        // 模板只支持 mail
        $template = $this->createTemplate(['channels' => ['mail']]);

        // Job 尝试使用 sms（不在模板支持范围内）
        $job = new NotificationJob(
            'user',
            $user->id,
            $template->id,
            'sms',
            ['username' => $user->username],
            DefaultNotificationBuilder::class
        );

        $job->handle(app(NotificationRepository::class), app(ChannelManager::class));

        // 验证没有为这个模板和用户创建通知
        $notification = Notification::where('template_id', $template->id)
            ->where('notifiable_type', 'user')
            ->where('notifiable_id', $user->id)
            ->first();
        $this->assertNull($notification);
    }

    public function test_handles_channel_exception_gracefully(): void
    {
        $user = $this->createUser();
        $template = $this->createTemplate();

        // Mock MailChannel to throw exception
        $this->mock(MailChannel::class, function ($mock) {
            $mock->shouldReceive('send')
                ->once()
                ->andThrow(new Exception('Channel error'));
        });

        $job = new NotificationJob(
            'user',
            $user->id,
            $template->id,
            'mail',
            ['username' => $user->username],
            DefaultNotificationBuilder::class
        );

        $job->handle(app(NotificationRepository::class), app(ChannelManager::class));

        // 验证通知标记为失败，并记录错误
        $notification = Notification::where('notifiable_id', $user->id)->first();
        $this->assertNotNull($notification);
        $this->assertEquals(Notification::STATUS_FAILED, $notification->status);
        $this->assertEquals('mail', $notification->data['result']['channel']);
        $this->assertEquals(Notification::STATUS_FAILED, $notification->data['result']['status']);
        $this->assertEquals('发送失败，请稍后重试', $notification->data['result']['message']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
