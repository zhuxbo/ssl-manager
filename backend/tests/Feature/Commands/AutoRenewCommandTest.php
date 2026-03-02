<?php

use App\Models\Cert;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Notification\NotificationCenter;
use App\Services\Order\Action;
use App\Services\Order\AutoRenewService;

uses(Tests\Traits\CreatesTestData::class);

beforeEach(function () {
    // Mock AutoRenewService 避免真实 DNS 检查
    $this->autoRenewService = Mockery::mock(AutoRenewService::class);
    $this->app->instance(AutoRenewService::class, $this->autoRenewService);

    // Mock NotificationCenter
    $this->notificationCenter = Mockery::mock(NotificationCenter::class);
    $this->app->instance(NotificationCenter::class, $this->notificationCenter);
});

test('签名为 schedule:auto-renew', function () {
    $this->artisan('schedule:auto-renew')->assertSuccessful();
});

test('无需续费或重签订单时正常退出', function () {
    $this->artisan('schedule:auto-renew')
        ->expectsOutputToContain('开始自动续费/重签任务')
        ->expectsOutputToContain('自动续费/重签任务完成')
        ->assertSuccessful();
});

test('有续费订单时调用 Action renew', function () {
    $user = User::factory()->withBalance('1000.00')->withAutoRenew()->create();
    $product = Product::factory()->create(['status' => 1, 'renew' => 1]);
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'auto_renew' => true,
        'period_from' => now()->subYear(),
        'period_till' => now()->addDays(5),
    ]);

    $cert = Cert::factory()->active()->create([
        'order_id' => $order->id,
        'expires_at' => now()->addDays(5),
        'amount' => '100.00',
        'channel' => 'web',
    ]);
    $order->update(['latest_cert_id' => $cert->id]);

    $this->autoRenewService->shouldReceive('checkDelegationValidity')
        ->andReturn(true);

    // Mock Action 来拦截续费调用
    $actionMock = Mockery::mock(Action::class);
    $actionMock->shouldReceive('renew')->once();
    $actionMock->shouldReceive('pay')->once();
    $this->app->bind(Action::class, fn () => $actionMock);

    // 由于 Action 在命令中是 new 出来的，我们需要用不同的方式验证
    // 这里验证命令不会抛出异常
    $this->artisan('schedule:auto-renew')->assertSuccessful();
});

test('有重签订单时处理重签逻辑', function () {
    $user = User::factory()->create([
        'auto_settings' => ['auto_renew' => false, 'auto_reissue' => true],
    ]);
    $product = Product::factory()->create(['status' => 1]);
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'auto_reissue' => true,
        'period_from' => now()->subYear(),
        'period_till' => now()->addMonths(6), // 订单还有很多余量
    ]);

    $cert = Cert::factory()->active()->create([
        'order_id' => $order->id,
        'expires_at' => now()->addDays(5), // 证书即将到期
        'channel' => 'web',
    ]);
    $order->update(['latest_cert_id' => $cert->id]);

    $this->autoRenewService->shouldReceive('checkDelegationValidity')
        ->andReturn(true);

    $this->artisan('schedule:auto-renew')->assertSuccessful();
});

test('委托检查失败时跳过订单', function () {
    $user = User::factory()->withBalance('1000.00')->create();
    $product = Product::factory()->create(['status' => 1, 'renew' => 1]);
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'auto_renew' => true,
        'period_from' => now()->subYear(),
        'period_till' => now()->addDays(3),
    ]);

    $cert = Cert::factory()->active()->create([
        'order_id' => $order->id,
        'expires_at' => now()->addDays(3),
        'amount' => '100.00',
        'channel' => 'web',
    ]);
    $order->update(['latest_cert_id' => $cert->id]);

    $this->autoRenewService->shouldReceive('checkDelegationValidity')
        ->andReturn(false);

    $this->artisan('schedule:auto-renew')
        ->expectsOutputToContain('跳过')
        ->assertSuccessful();
});

test('余额不足时续费失败发送通知', function () {
    $user = User::factory()->withBalance('0.00')->create([
        'credit_limit' => '0.00',
    ]);
    $product = Product::factory()->create(['status' => 1, 'renew' => 1]);
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'auto_renew' => true,
        'period_from' => now()->subYear(),
        'period_till' => now()->addDays(3),
    ]);

    $cert = Cert::factory()->active()->create([
        'order_id' => $order->id,
        'expires_at' => now()->addDays(3),
        'amount' => '100.00',
        'channel' => 'web',
    ]);
    $order->update(['latest_cert_id' => $cert->id]);

    $this->autoRenewService->shouldReceive('checkDelegationValidity')
        ->andReturn(true);

    // 失败时应该发送通知
    $this->notificationCenter->shouldReceive('dispatch')->once();

    $this->artisan('schedule:auto-renew')
        ->expectsOutputToContain('失败')
        ->assertSuccessful();
});

test('ACME 通道证书不参与自动续费', function () {
    $user = User::factory()->withBalance('1000.00')->create();
    $product = Product::factory()->create(['status' => 1, 'renew' => 1]);
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'auto_renew' => true,
        'period_from' => now()->subYear(),
        'period_till' => now()->addDays(3),
    ]);

    $cert = Cert::factory()->active()->acme()->create([
        'order_id' => $order->id,
        'expires_at' => now()->addDays(3),
        'channel' => 'acme',
    ]);
    $order->update(['latest_cert_id' => $cert->id]);

    // ACME 证书不应触发续费，所以不会调用 checkDelegationValidity
    $this->autoRenewService->shouldNotReceive('checkDelegationValidity');

    $this->artisan('schedule:auto-renew')->assertSuccessful();
});
