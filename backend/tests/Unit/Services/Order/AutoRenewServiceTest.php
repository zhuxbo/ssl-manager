<?php

use App\Services\Delegation\CnameDelegationService;
use App\Services\Order\AutoRenewService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Traits\CreatesTestData;

uses(Tests\TestCase::class, CreatesTestData::class, RefreshDatabase::class)->group('database');

beforeEach(function () {
    $this->seed = true;
    $this->seeder = DatabaseSeeder::class;
    $this->service = app(AutoRenewService::class);
});

afterEach(function () {
    Mockery::close();
});

// ==================== willAutoRenewExecute ====================

test('will auto renew execute returns false when disabled', function () {
    $user = $this->createTestUser(['auto_settings' => ['auto_renew' => false, 'auto_reissue' => false]]);
    $product = $this->createTestProduct(['status' => 1, 'renew' => 1]);
    $order = $this->createTestOrder($user, $product, [
        'auto_renew' => false,
        'period_till' => now()->addDays(30),
    ]);
    $this->createTestCert($order, [
        'channel' => 'api',
        'expires_at' => now()->addDays(28),
    ]);

    $order->refresh();
    $result = $this->service->willAutoRenewExecute($order, $user);

    expect($result)->toBeFalse();
});

test('will auto renew execute order setting overrides user', function () {
    $user = $this->createTestUser(['auto_settings' => ['auto_renew' => true, 'auto_reissue' => false]]);
    $product = $this->createTestProduct(['status' => 1, 'renew' => 1]);
    $order = $this->createTestOrder($user, $product, [
        'auto_renew' => false, // 订单级别禁用
        'period_till' => now()->addDays(30),
    ]);
    $this->createTestCert($order, [
        'channel' => 'api',
        'expires_at' => now()->addDays(28),
    ]);

    $order->refresh();
    $result = $this->service->willAutoRenewExecute($order, $user);

    expect($result)->toBeFalse();
});

test('will auto renew execute falls back to user setting', function () {
    $user = $this->createTestUser(['auto_settings' => ['auto_renew' => true, 'auto_reissue' => false]]);
    $product = $this->createTestProduct(['status' => 1, 'renew' => 1]);
    $order = $this->createTestOrder($user, $product, [
        'auto_renew' => null, // 回落到用户设置
        'period_till' => now()->addDays(30),
    ]);
    $this->createTestCert($order, [
        'channel' => 'api',
        'expires_at' => now()->addDays(28),
    ]);

    $order->refresh();
    $result = $this->service->willAutoRenewExecute($order, $user);

    expect($result)->toBeTrue();
});

test('will auto renew execute returns false when product disabled', function () {
    $user = $this->createTestUser(['auto_settings' => ['auto_renew' => true, 'auto_reissue' => false]]);
    $product = $this->createTestProduct(['status' => 0, 'renew' => 1]); // 产品禁用
    $order = $this->createTestOrder($user, $product, [
        'auto_renew' => true,
        'period_till' => now()->addDays(30),
    ]);
    $this->createTestCert($order, [
        'channel' => 'api',
        'expires_at' => now()->addDays(28),
    ]);

    $order->refresh();
    $result = $this->service->willAutoRenewExecute($order, $user);

    expect($result)->toBeFalse();
});

test('will auto renew execute returns false when product not renewable', function () {
    $user = $this->createTestUser(['auto_settings' => ['auto_renew' => true, 'auto_reissue' => false]]);
    $product = $this->createTestProduct(['status' => 1, 'renew' => 0]); // 不支持续费
    $order = $this->createTestOrder($user, $product, [
        'auto_renew' => true,
        'period_till' => now()->addDays(30),
    ]);
    $this->createTestCert($order, [
        'channel' => 'api',
        'expires_at' => now()->addDays(28),
    ]);

    $order->refresh();
    $result = $this->service->willAutoRenewExecute($order, $user);

    expect($result)->toBeFalse();
});

test('will auto renew execute returns false for acme channel', function () {
    $user = $this->createTestUser(['auto_settings' => ['auto_renew' => true, 'auto_reissue' => false]]);
    $product = $this->createTestProduct(['status' => 1, 'renew' => 1]);
    $order = $this->createTestOrder($user, $product, [
        'auto_renew' => true,
        'period_till' => now()->addDays(30),
    ]);
    $this->createTestCert($order, [
        'channel' => 'acme', // ACME 渠道
        'expires_at' => now()->addDays(28),
    ]);

    $order->refresh();
    $result = $this->service->willAutoRenewExecute($order, $user);

    expect($result)->toBeFalse();
});

test('will auto renew execute returns false when period diff too large', function () {
    $user = $this->createTestUser(['auto_settings' => ['auto_renew' => true, 'auto_reissue' => false]]);
    $product = $this->createTestProduct(['status' => 1, 'renew' => 1]);
    $order = $this->createTestOrder($user, $product, [
        'auto_renew' => true,
        'period_till' => now()->addDays(30),
    ]);
    $this->createTestCert($order, [
        'channel' => 'api',
        'expires_at' => now()->addDays(20), // period_till - expires_at = 10天，超过7天阈值
    ]);

    $order->refresh();
    $result = $this->service->willAutoRenewExecute($order, $user);

    expect($result)->toBeFalse();
});

test('will auto renew execute returns true when period diff within threshold', function () {
    $user = $this->createTestUser(['auto_settings' => ['auto_renew' => true, 'auto_reissue' => false]]);
    $product = $this->createTestProduct(['status' => 1, 'renew' => 1]);
    $order = $this->createTestOrder($user, $product, [
        'auto_renew' => true,
        'period_till' => now()->addDays(30),
    ]);
    $this->createTestCert($order, [
        'channel' => 'api',
        'expires_at' => now()->addDays(25), // 差5天，在7天阈值内
    ]);

    $order->refresh();
    $result = $this->service->willAutoRenewExecute($order, $user);

    expect($result)->toBeTrue();
});

test('will auto renew execute boundary 7 days', function () {
    $user = $this->createTestUser(['auto_settings' => ['auto_renew' => true, 'auto_reissue' => false]]);
    $product = $this->createTestProduct(['status' => 1, 'renew' => 1]);
    $order = $this->createTestOrder($user, $product, [
        'auto_renew' => true,
        'period_till' => now()->addDays(30),
    ]);
    $this->createTestCert($order, [
        'channel' => 'api',
        'expires_at' => now()->addDays(23), // period_till - expires_at = 7天，刚好达到阈值
    ]);

    $order->refresh();
    $result = $this->service->willAutoRenewExecute($order, $user);

    expect($result)->toBeFalse(); // 等于7天应该返回false（不续费）
});

// ==================== willAutoReissueExecute ====================

test('will auto reissue execute returns false when disabled', function () {
    $user = $this->createTestUser(['auto_settings' => ['auto_renew' => false, 'auto_reissue' => false]]);
    $product = $this->createTestProduct(['status' => 1]);
    $order = $this->createTestOrder($user, $product, [
        'auto_reissue' => false,
        'period_till' => now()->addDays(30),
    ]);
    $this->createTestCert($order, [
        'channel' => 'api',
        'expires_at' => now()->addDays(10), // 差20天
    ]);

    $order->refresh();
    $result = $this->service->willAutoReissueExecute($order, $user);

    expect($result)->toBeFalse();
});

test('will auto reissue execute order setting overrides user', function () {
    $user = $this->createTestUser(['auto_settings' => ['auto_renew' => false, 'auto_reissue' => true]]);
    $product = $this->createTestProduct(['status' => 1]);
    $order = $this->createTestOrder($user, $product, [
        'auto_reissue' => false, // 订单级别禁用
        'period_till' => now()->addDays(30),
    ]);
    $this->createTestCert($order, [
        'channel' => 'api',
        'expires_at' => now()->addDays(10),
    ]);

    $order->refresh();
    $result = $this->service->willAutoReissueExecute($order, $user);

    expect($result)->toBeFalse();
});

test('will auto reissue execute falls back to user setting', function () {
    $user = $this->createTestUser(['auto_settings' => ['auto_renew' => false, 'auto_reissue' => true]]);
    $product = $this->createTestProduct(['status' => 1]);
    $order = $this->createTestOrder($user, $product, [
        'auto_reissue' => null, // 回落到用户设置
        'period_till' => now()->addDays(30),
    ]);
    $this->createTestCert($order, [
        'channel' => 'api',
        'expires_at' => now()->addDays(10), // period_till - expires_at = 20天，超过7天
    ]);

    $order->refresh();
    $result = $this->service->willAutoReissueExecute($order, $user);

    expect($result)->toBeTrue();
});

test('will auto reissue execute returns false when product disabled', function () {
    $user = $this->createTestUser(['auto_settings' => ['auto_renew' => false, 'auto_reissue' => true]]);
    $product = $this->createTestProduct(['status' => 0]); // 产品禁用
    $order = $this->createTestOrder($user, $product, [
        'auto_reissue' => true,
        'period_till' => now()->addDays(30),
    ]);
    $this->createTestCert($order, [
        'channel' => 'api',
        'expires_at' => now()->addDays(10),
    ]);

    $order->refresh();
    $result = $this->service->willAutoReissueExecute($order, $user);

    expect($result)->toBeFalse();
});

test('will auto reissue execute returns false for acme channel', function () {
    $user = $this->createTestUser(['auto_settings' => ['auto_renew' => false, 'auto_reissue' => true]]);
    $product = $this->createTestProduct(['status' => 1]);
    $order = $this->createTestOrder($user, $product, [
        'auto_reissue' => true,
        'period_till' => now()->addDays(30),
    ]);
    $this->createTestCert($order, [
        'channel' => 'acme', // ACME 渠道
        'expires_at' => now()->addDays(10),
    ]);

    $order->refresh();
    $result = $this->service->willAutoReissueExecute($order, $user);

    expect($result)->toBeFalse();
});

test('will auto reissue execute returns false when period diff too small', function () {
    $user = $this->createTestUser(['auto_settings' => ['auto_renew' => false, 'auto_reissue' => true]]);
    $product = $this->createTestProduct(['status' => 1]);
    $order = $this->createTestOrder($user, $product, [
        'auto_reissue' => true,
        'period_till' => now()->addDays(30),
    ]);
    $this->createTestCert($order, [
        'channel' => 'api',
        'expires_at' => now()->addDays(28), // 差2天，小于7天阈值
    ]);

    $order->refresh();
    $result = $this->service->willAutoReissueExecute($order, $user);

    expect($result)->toBeFalse();
});

test('will auto reissue execute returns true when period diff exceeds threshold', function () {
    $user = $this->createTestUser(['auto_settings' => ['auto_renew' => false, 'auto_reissue' => true]]);
    $product = $this->createTestProduct(['status' => 1]);
    $order = $this->createTestOrder($user, $product, [
        'auto_reissue' => true,
        'period_till' => now()->addDays(30),
    ]);
    $this->createTestCert($order, [
        'channel' => 'api',
        'expires_at' => now()->addDays(10), // period_till - expires_at = 20天，超过7天
    ]);

    $order->refresh();
    $result = $this->service->willAutoReissueExecute($order, $user);

    expect($result)->toBeTrue();
});

test('will auto reissue execute boundary 7 days', function () {
    $user = $this->createTestUser(['auto_settings' => ['auto_renew' => false, 'auto_reissue' => true]]);
    $product = $this->createTestProduct(['status' => 1]);
    $order = $this->createTestOrder($user, $product, [
        'auto_reissue' => true,
        'period_till' => now()->addDays(30),
    ]);
    $this->createTestCert($order, [
        'channel' => 'api',
        'expires_at' => now()->addDays(23), // 正好差7天
    ]);

    $order->refresh();
    $result = $this->service->willAutoReissueExecute($order, $user);

    expect($result)->toBeFalse(); // 等于7天应该返回false
});

// ==================== checkDelegationValidity ====================

test('check delegation validity returns false when no delegation', function () {
    $user = $this->createTestUser();
    // 不创建委托记录

    $result = $this->service->checkDelegationValidity($user->id, 'example.com', 'sectigo');

    expect($result)->toBeFalse();
});

test('check delegation validity returns false when verification fails', function () {
    $user = $this->createTestUser();
    $this->createTestDelegation($user, [
        'zone' => 'example.com',
        'prefix' => '_pki-validation',
        'valid' => true,
    ]);

    // 实际 CNAME 验证会失败（因为没有真实的 DNS 记录）
    $result = $this->service->checkDelegationValidity($user->id, 'example.com', 'sectigo');

    expect($result)->toBeFalse();
});

test('check delegation validity uses correct prefix for ca', function () {
    $user = $this->createTestUser();

    // 创建 Sectigo 的委托记录（使用 _pki-validation 前缀）
    $this->createTestDelegation($user, [
        'zone' => 'example.com',
        'prefix' => '_pki-validation',
        'valid' => true,
    ]);

    // 使用 ACME CA（需要 _acme-challenge 前缀），应该找不到
    $mockDelegationService = Mockery::mock(CnameDelegationService::class)->makePartial();
    $mockDelegationService->shouldReceive('findDelegation')
        ->with($user->id, 'example.com', '_acme-challenge')
        ->andReturn(null);

    $service = new AutoRenewService($mockDelegationService);
    $result = $service->checkDelegationValidity($user->id, 'example.com', 'letsencrypt');

    expect($result)->toBeFalse();
});

test('check delegation validity handles multiple domains', function () {
    $user = $this->createTestUser();
    $this->createTestDelegation($user, [
        'zone' => 'example.com',
        'prefix' => '_pki-validation',
        'valid' => true,
    ]);
    // 不创建 other.com 的委托

    $result = $this->service->checkDelegationValidity(
        $user->id,
        'example.com,other.com',
        'sectigo'
    );

    expect($result)->toBeFalse();
});

test('check delegation validity skips empty domains', function () {
    $user = $this->createTestUser();

    // 没有委托记录
    $result = $this->service->checkDelegationValidity($user->id, ',,,', 'sectigo');

    // 所有域名都是空的，应该返回 true（没有需要验证的）
    expect($result)->toBeTrue();
});

// ==================== isAutoRenewEnabled ====================

test('is auto renew enabled order setting', function () {
    $user = $this->createTestUser(['auto_settings' => ['auto_renew' => false, 'auto_reissue' => false]]);
    $product = $this->createTestProduct();
    $order = $this->createTestOrder($user, $product, ['auto_renew' => true]);

    $result = $this->service->isAutoRenewEnabled($order, $user);

    expect($result)->toBeTrue();
});

test('is auto renew enabled falls back to user setting', function () {
    $user = $this->createTestUser(['auto_settings' => ['auto_renew' => true, 'auto_reissue' => false]]);
    $product = $this->createTestProduct();
    $order = $this->createTestOrder($user, $product, ['auto_renew' => null]);

    $result = $this->service->isAutoRenewEnabled($order, $user);

    expect($result)->toBeTrue();
});

test('is auto renew enabled returns false when both disabled', function () {
    $user = $this->createTestUser(['auto_settings' => ['auto_renew' => false, 'auto_reissue' => false]]);
    $product = $this->createTestProduct();
    $order = $this->createTestOrder($user, $product, ['auto_renew' => null]);

    $result = $this->service->isAutoRenewEnabled($order, $user);

    expect($result)->toBeFalse();
});
