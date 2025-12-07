<?php

namespace Tests\Feature;

use App\Jobs\NotificationJob;
use App\Models\Notification;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Services\Notification\ChannelManager;
use App\Services\Notification\Channels\MailChannel;
use App\Services\Notification\Channels\SmsChannel;
use App\Services\Notification\DTOs\NotificationIntent;
use App\Services\Notification\NotificationCenter;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use ReflectionClass;
use ReflectionException;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = DatabaseSeeder::class;

    protected function createUser(array $overrides = []): User
    {
        $notificationSettings = $overrides['notification_settings'] ?? null;
        unset($overrides['notification_settings']);

        $email = array_key_exists('email', $overrides)
            ? $overrides['email']
            : uniqid().'@example.com';
        unset($overrides['email']);

        $user = User::firstOrCreate(
            ['email' => $email],
            array_merge([
                'username' => $overrides['username'] ?? 'user_'.uniqid(),
                'password' => 'secret',
                'join_at' => now(),
            ], $overrides)
        );

        if ($notificationSettings !== null) {
            $user->notification_settings = $notificationSettings;
            $user->save();
        }

        return $user;
    }

    protected function seedTemplate(string $code, string $name, string $content, array $channels = ['mail']): void
    {
        $existing = NotificationTemplate::query()
            ->where('code', $code)
            ->get()
            ->first(function ($item) use ($channels) {
                $existingChannels = collect($item->channels)->sort()->values()->toArray();
                $target = collect($channels)->sort()->values()->toArray();

                return $existingChannels === $target;
            });

        if ($existing) {
            $existing->update([
                'name' => $name,
                'content' => $content,
                'variables' => ['username'],
                'status' => 1,
            ]);

            return;
        }

        NotificationTemplate::create([
            'code' => $code,
            'name' => $name,
            'content' => $content,
            'variables' => ['username'],
            'status' => 1,
            'channels' => $channels,
        ]);
    }

    public function test_dispatches_job_when_guard_passes(): void
    {
        Queue::fake();
        $user = $this->createUser(['mobile' => substr('1380'.uniqid(), 0, 20)]);
        $this->seedTemplate('test_mail', '测试模板', 'Hi {{ $username }}');

        $this->mockMailChannel();

        app(NotificationCenter::class)->dispatch(new NotificationIntent('test_mail', 'user', $user->id));

        Queue::assertPushed(NotificationJob::class, function ($job) use ($user) {
            return $this->getPrivateProperty($job, 'notifiableId') === $user->id
                && $this->getPrivateProperty($job, 'channel') === 'mail';
        });
    }

    public function test_guard_blocks_when_contact_missing(): void
    {
        Queue::fake();
        $user = $this->createUser(['email' => null]);
        $this->seedTemplate('test_mail', '测试模板', 'Hi {{ $username }}');

        app(NotificationCenter::class)->dispatch(new NotificationIntent('test_mail', 'user', $user->id));

        Queue::assertNotPushed(NotificationJob::class);
    }

    public function test_dispatches_with_multi_channels(): void
    {
        Queue::fake();
        $user = $this->createUser(['mobile' => substr('1380'.uniqid(), 0, 20)]);
        $this->seedTemplate('test_multi', '多通道模板', 'Hi {{ $username }}', ['mail', 'sms']);

        $this->mockMailChannel();
        app()->bind(SmsChannel::class, fn () => new class extends SmsChannel
        {
            public function send(Notification $notification): array
            {
                return ['success' => true, 'message' => null];
            }

            public function isAvailable(): bool
            {
                return true;
            }
        });
        app()->forgetInstance(ChannelManager::class);

        app(NotificationCenter::class)->dispatch(new NotificationIntent('test_multi', 'user', $user->id));

        // 多通道会派发多个 Job，每个通道一个
        Queue::assertPushed(NotificationJob::class, 2);

        Queue::assertPushed(NotificationJob::class, function ($job) {
            $channel = $this->getPrivateProperty($job, 'channel');
            return in_array($channel, ['mail', 'sms']);
        });
    }

    public function test_uses_preferred_channels_when_provided(): void
    {
        Queue::fake();
        $user = $this->createUser(['mobile' => substr('1380'.uniqid(), 0, 20)]);
        $this->seedTemplate('test_selective', '多通道模板', 'Hi {{ $username }}', ['mail', 'sms']);

        $intent = new NotificationIntent('test_selective', 'user', $user->id, [], ['sms']);
        app(NotificationCenter::class)->dispatch($intent);

        Queue::assertPushed(NotificationJob::class, 1);
        Queue::assertPushed(NotificationJob::class, function ($job) {
            return $this->getPrivateProperty($job, 'channel') === 'sms';
        });
    }

    /**
     * @throws ReflectionException
     */
    protected function getPrivateProperty(object $object, string $property): mixed
    {
        $reflection = new ReflectionClass($object);
        $prop = $reflection->getProperty($property);

        return $prop->getValue($object);
    }

    protected function mockMailChannel(): void
    {
        app()->bind(MailChannel::class, fn () => new class extends MailChannel
        {
            public function send(Notification $notification): array
            {
                return ['code' => 1, 'msg' => '发送成功'];
            }

            public function isAvailable(): bool
            {
                return true;
            }
        });
        app()->forgetInstance(ChannelManager::class);
    }
}
