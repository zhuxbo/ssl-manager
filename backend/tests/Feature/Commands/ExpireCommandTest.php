<?php

use App\Models\Cert;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Notification\NotificationCenter;

test('标记已过期的证书状态为 expired', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
    ]);

    $cert = Cert::factory()->create([
        'order_id' => $order->id,
        'status' => 'active',
        'expires_at' => now()->subDay(),
    ]);

    // Mock NotificationCenter
    $notificationCenter = Mockery::mock(NotificationCenter::class);
    $notificationCenter->shouldReceive('dispatch')->zeroOrMoreTimes();
    $this->app->instance(NotificationCenter::class, $notificationCenter);

    $this->artisan('schedule:expire')->assertSuccessful();

    $cert->refresh();
    expect($cert->status)->toBe('expired');
});

test('未过期的证书状态不变', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
    ]);

    $cert = Cert::factory()->active()->create([
        'order_id' => $order->id,
        'expires_at' => now()->addDays(30),
    ]);

    $notificationCenter = Mockery::mock(NotificationCenter::class);
    $notificationCenter->shouldReceive('dispatch')->zeroOrMoreTimes();
    $this->app->instance(NotificationCenter::class, $notificationCenter);

    $this->artisan('schedule:expire')->assertSuccessful();

    $cert->refresh();
    expect($cert->status)->toBe('active');
});

test('即将到期的证书发送通知（14天内）', function () {
    $user = User::factory()->create(['email' => 'test@example.com']);
    $product = Product::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
    ]);

    // 13-14 天后到期的证书应该触发通知
    $cert = Cert::factory()->active()->create([
        'order_id' => $order->id,
        'expires_at' => now()->addDays(13)->addHours(12),
    ]);
    $order->update(['latest_cert_id' => $cert->id]);

    $notificationCenter = Mockery::mock(NotificationCenter::class);
    $notificationCenter->shouldReceive('dispatch')->atLeast()->once();
    $this->app->instance(NotificationCenter::class, $notificationCenter);

    $this->artisan('schedule:expire')->assertSuccessful();
});

test('无过期证书时正常退出', function () {
    $notificationCenter = Mockery::mock(NotificationCenter::class);
    $notificationCenter->shouldNotReceive('dispatch');
    $this->app->instance(NotificationCenter::class, $notificationCenter);

    $this->artisan('schedule:expire')->assertSuccessful();
});

test('多个到期时间段的证书都会触发通知', function () {
    $user = User::factory()->create(['email' => 'test@example.com']);
    $product = Product::factory()->create();

    // 创建不同到期时间段的订单/证书
    $timeRanges = [
        now()->addDays(13)->addHours(12), // 14天区段
        now()->addDays(6)->addHours(12),  // 7天区段
        now()->addDays(2)->addHours(12),  // 3天区段
        now()->addHours(12),               // 1天区段
    ];

    foreach ($timeRanges as $expiresAt) {
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);
        $cert = Cert::factory()->active()->create([
            'order_id' => $order->id,
            'expires_at' => $expiresAt,
        ]);
        $order->update(['latest_cert_id' => $cert->id]);
    }

    $notificationCenter = Mockery::mock(NotificationCenter::class);
    // 同一用户只发一次通知（去重）
    $notificationCenter->shouldReceive('dispatch')->once();
    $this->app->instance(NotificationCenter::class, $notificationCenter);

    $this->artisan('schedule:expire')->assertSuccessful();
});
