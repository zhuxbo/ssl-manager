<?php

namespace Tests\Unit\Services\Order;

use App\Services\Delegation\CnameDelegationService;
use App\Services\Order\AutoRenewService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * AutoRenewService 测试
 * 需要数据库连接
 */
#[Group('database')]
class AutoRenewServiceTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = DatabaseSeeder::class;

    protected AutoRenewService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AutoRenewService::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ==================== willAutoRenewExecute ====================

    public function test_will_auto_renew_execute_returns_false_when_disabled(): void
    {
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

        $this->assertFalse($result);
    }

    public function test_will_auto_renew_execute_order_setting_overrides_user(): void
    {
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

        $this->assertFalse($result);
    }

    public function test_will_auto_renew_execute_falls_back_to_user_setting(): void
    {
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

        $this->assertTrue($result);
    }

    public function test_will_auto_renew_execute_returns_false_when_product_disabled(): void
    {
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

        $this->assertFalse($result);
    }

    public function test_will_auto_renew_execute_returns_false_when_product_not_renewable(): void
    {
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

        $this->assertFalse($result);
    }

    public function test_will_auto_renew_execute_returns_false_for_acme_channel(): void
    {
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

        $this->assertFalse($result);
    }

    public function test_will_auto_renew_execute_returns_false_when_period_diff_too_large(): void
    {
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

        $this->assertFalse($result);
    }

    public function test_will_auto_renew_execute_returns_true_when_period_diff_within_threshold(): void
    {
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

        $this->assertTrue($result);
    }

    public function test_will_auto_renew_execute_boundary_7_days(): void
    {
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

        $this->assertFalse($result); // 等于7天应该返回false（不续费）
    }

    // ==================== willAutoReissueExecute ====================

    public function test_will_auto_reissue_execute_returns_false_when_disabled(): void
    {
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

        $this->assertFalse($result);
    }

    public function test_will_auto_reissue_execute_order_setting_overrides_user(): void
    {
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

        $this->assertFalse($result);
    }

    public function test_will_auto_reissue_execute_falls_back_to_user_setting(): void
    {
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

        $this->assertTrue($result);
    }

    public function test_will_auto_reissue_execute_returns_false_when_product_disabled(): void
    {
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

        $this->assertFalse($result);
    }

    public function test_will_auto_reissue_execute_returns_false_for_acme_channel(): void
    {
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

        $this->assertFalse($result);
    }

    public function test_will_auto_reissue_execute_returns_false_when_period_diff_too_small(): void
    {
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

        $this->assertFalse($result);
    }

    public function test_will_auto_reissue_execute_returns_true_when_period_diff_exceeds_threshold(): void
    {
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

        $this->assertTrue($result);
    }

    public function test_will_auto_reissue_execute_boundary_7_days(): void
    {
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

        $this->assertFalse($result); // 等于7天应该返回false
    }

    // ==================== checkDelegationValidity ====================

    public function test_check_delegation_validity_returns_false_when_no_delegation(): void
    {
        $user = $this->createTestUser();
        // 不创建委托记录

        $result = $this->service->checkDelegationValidity($user->id, 'example.com', 'sectigo');

        $this->assertFalse($result);
    }

    public function test_check_delegation_validity_returns_false_when_verification_fails(): void
    {
        $user = $this->createTestUser();
        $this->createTestDelegation($user, [
            'zone' => 'example.com',
            'prefix' => '_pki-validation',
            'valid' => true,
        ]);

        // 实际 CNAME 验证会失败（因为没有真实的 DNS 记录）
        $result = $this->service->checkDelegationValidity($user->id, 'example.com', 'sectigo');

        $this->assertFalse($result);
    }

    public function test_check_delegation_validity_uses_correct_prefix_for_ca(): void
    {
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

        $this->assertFalse($result);
    }

    public function test_check_delegation_validity_handles_multiple_domains(): void
    {
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

        $this->assertFalse($result);
    }

    public function test_check_delegation_validity_skips_empty_domains(): void
    {
        $user = $this->createTestUser();

        // 没有委托记录
        $result = $this->service->checkDelegationValidity($user->id, ',,,', 'sectigo');

        // 所有域名都是空的，应该返回 true（没有需要验证的）
        $this->assertTrue($result);
    }

    // ==================== isAutoRenewEnabled ====================

    public function test_is_auto_renew_enabled_order_setting(): void
    {
        $user = $this->createTestUser(['auto_settings' => ['auto_renew' => false, 'auto_reissue' => false]]);
        $product = $this->createTestProduct();
        $order = $this->createTestOrder($user, $product, ['auto_renew' => true]);

        $result = $this->service->isAutoRenewEnabled($order, $user);

        $this->assertTrue($result);
    }

    public function test_is_auto_renew_enabled_falls_back_to_user_setting(): void
    {
        $user = $this->createTestUser(['auto_settings' => ['auto_renew' => true, 'auto_reissue' => false]]);
        $product = $this->createTestProduct();
        $order = $this->createTestOrder($user, $product, ['auto_renew' => null]);

        $result = $this->service->isAutoRenewEnabled($order, $user);

        $this->assertTrue($result);
    }

    public function test_is_auto_renew_enabled_returns_false_when_both_disabled(): void
    {
        $user = $this->createTestUser(['auto_settings' => ['auto_renew' => false, 'auto_reissue' => false]]);
        $product = $this->createTestProduct();
        $order = $this->createTestOrder($user, $product, ['auto_renew' => null]);

        $result = $this->service->isAutoRenewEnabled($order, $user);

        $this->assertFalse($result);
    }
}
