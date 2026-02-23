<?php

namespace Tests\Unit\Services\Acme;

use App\Models\Acme\AcmeAccount;
use App\Models\Order;
use App\Models\ProductPrice;
use App\Models\Transaction;
use App\Services\Acme\BillingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * BillingService 测试（需要数据库）
 */
#[Group('database')]
class BillingServiceTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = DatabaseSeeder::class;

    private BillingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BillingService::class);
    }

    public function test_can_issue_certificate_finds_valid_order_with_support_acme(): void
    {
        $user = $this->createTestUser(['balance' => '500.00']);
        $product = $this->createTestProduct(['support_acme' => 1]);
        $order = $this->createTestOrder($user, $product, [
            'period_till' => now()->addYear(),
        ]);
        $this->createTestCert($order, ['channel' => 'acme']);

        $account = AcmeAccount::create([
            'user_id' => $user->id,
            'key_id' => 'test_key_'.uniqid(),
            'public_key' => ['kty' => 'RSA'],
            'status' => 'valid',
        ]);

        $result = $this->service->canIssueCertificate($account);

        $this->assertTrue($result['allowed']);
        $this->assertEquals($order->id, $result['order']->id);
    }

    public function test_can_issue_certificate_rejects_non_support_acme_product(): void
    {
        $user = $this->createTestUser(['balance' => '500.00']);
        // 不设置 support_acme=1
        $product = $this->createTestProduct(['support_acme' => 0]);
        $order = $this->createTestOrder($user, $product, [
            'period_till' => now()->addYear(),
        ]);
        $this->createTestCert($order, ['channel' => 'acme']);

        $account = AcmeAccount::create([
            'user_id' => $user->id,
            'key_id' => 'test_key_'.uniqid(),
            'public_key' => ['kty' => 'RSA'],
            'status' => 'valid',
        ]);

        $result = $this->service->canIssueCertificate($account);

        $this->assertFalse($result['allowed']);
    }

    public function test_try_auto_renew_uses_standard_transaction_flow(): void
    {
        $user = $this->createTestUser(['balance' => '500.00']);
        $product = $this->createTestProduct([
            'support_acme' => 1,
            'standard_min' => 1,
            'total_min' => 1,
        ]);
        $this->createProductPrice($product->id, $user);

        $lastOrder = $this->createTestOrder($user, $product, [
            'period_till' => now()->subDays(1),
            'auto_renew' => true,
        ]);
        $this->createTestCert($lastOrder, ['channel' => 'acme']);

        $initialBalance = (float) $user->balance;

        $result = $this->service->tryAutoRenew($user, $lastOrder);

        $this->assertTrue($result['success']);
        $this->assertInstanceOf(Order::class, $result['order']);

        // 验证使用了标准 Transaction（而非 fund->decrement）
        $transaction = Transaction::where('transaction_id', $result['order']->id)
            ->where('type', 'order')
            ->first();
        $this->assertNotNull($transaction, 'Should use Transaction::create for billing');

        // 验证 balance_before 和 balance_after 在 Transaction 中记录
        $this->assertNotNull($transaction->balance_before);
        $this->assertNotNull($transaction->balance_after);

        // 验证新订单有 EAB
        $newOrder = $result['order']->fresh();
        $this->assertNotNull($newOrder->eab_kid);
    }

    public function test_try_auto_renew_fails_with_insufficient_balance(): void
    {
        $user = $this->createTestUser(['balance' => '0.00']);
        $product = $this->createTestProduct(['support_acme' => 1]);
        $this->createProductPrice($product->id, $user, '100.00');

        $lastOrder = $this->createTestOrder($user, $product, [
            'period_till' => now()->subDays(1),
            'auto_renew' => true,
        ]);
        $this->createTestCert($lastOrder, ['channel' => 'acme']);

        $result = $this->service->tryAutoRenew($user, $lastOrder);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('balance', strtolower($result['message']));
    }

    /**
     * 创建产品价格
     */
    private function createProductPrice(int $productId, $user, string $price = '100.00'): void
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
}
