<?php

use App\Models\Acme\Account;
use App\Models\Order;
use App\Models\ProductPrice;
use App\Models\Transaction;
use App\Services\Acme\BillingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Traits\CreatesTestData;

uses(Tests\TestCase::class, CreatesTestData::class, RefreshDatabase::class)->group('database');

beforeEach(function () {
    $this->seed = true;
    $this->seeder = DatabaseSeeder::class;
    $this->service = app(BillingService::class);
});

/**
 * 创建产品价格（BillingService 测试用）
 */
function createBillingProductPrice(int $productId, $user, string $price = '100.00'): void
{
    ProductPrice::create([
        'product_id' => $productId,
        'level_code' => $user->level_code ?? 'standard',
        'period' => 12,
        'price' => $price,
        'alternative_standard_price' => '10.00',
        'alternative_wildcard_price' => '20.00',
    ]);
}

test('can issue certificate finds valid order with support acme', function () {
    $user = $this->createTestUser(['balance' => '500.00']);
    $product = $this->createTestProduct(['support_acme' => 1]);
    $order = $this->createTestOrder($user, $product, [
        'period_till' => now()->addYear(),
    ]);
    $this->createTestCert($order, ['channel' => 'acme']);

    $account = Account::create([
        'user_id' => $user->id,
        'order_id' => $order->id,
        'key_id' => 'test_key_'.uniqid(),
        'public_key' => ['kty' => 'RSA'],
        'status' => 'valid',
    ]);

    $result = $this->service->canIssueCertificate($account);

    expect($result['allowed'])->toBeTrue();
    expect($result['order']->id)->toBe($order->id);
});

test('can issue certificate rejects non support acme product', function () {
    $user = $this->createTestUser(['balance' => '500.00']);
    // 不设置 support_acme=1
    $product = $this->createTestProduct(['support_acme' => 0]);
    $order = $this->createTestOrder($user, $product, [
        'period_till' => now()->addYear(),
    ]);
    $this->createTestCert($order, ['channel' => 'acme']);

    $account = Account::create([
        'user_id' => $user->id,
        'key_id' => 'test_key_'.uniqid(),
        'public_key' => ['kty' => 'RSA'],
        'status' => 'valid',
    ]);

    $result = $this->service->canIssueCertificate($account);

    expect($result['allowed'])->toBeFalse();
});

test('try auto renew uses standard transaction flow', function () {
    $user = $this->createTestUser(['balance' => '500.00']);
    $product = $this->createTestProduct([
        'support_acme' => 1,
        'standard_min' => 1,
        'total_min' => 1,
    ]);
    createBillingProductPrice($product->id, $user);

    $lastOrder = $this->createTestOrder($user, $product, [
        'period_till' => now()->subDays(1),
        'auto_renew' => true,
    ]);
    $this->createTestCert($lastOrder, ['channel' => 'acme']);

    $initialBalance = (float) $user->balance;

    $result = $this->service->tryAutoRenew($user, $lastOrder);

    expect($result['code'])->toBe(1);
    expect($result['data']['order'])->toBeInstanceOf(Order::class);

    // 验证新订单有 EAB
    $newOrder = $result['data']['order']->fresh();
    expect($newOrder->eab_kid)->not->toBeNull();
});

test('try auto renew fails when user has no email', function () {
    $user = $this->createTestUser(['balance' => '0.00']);
    // 清除 email 以触发失败
    $user->update(['email' => '']);
    $user->refresh();

    $product = $this->createTestProduct(['support_acme' => 1]);
    createBillingProductPrice($product->id, $user, '100.00');

    $lastOrder = $this->createTestOrder($user, $product, [
        'period_till' => now()->subDays(1),
        'auto_renew' => true,
    ]);
    $this->createTestCert($lastOrder, ['channel' => 'acme']);

    $result = $this->service->tryAutoRenew($user, $lastOrder);

    expect($result['code'])->toBe(0);
    expect(strtolower($result['msg']))->toContain('email');
});
