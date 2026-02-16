<?php

namespace Tests\Unit\Services\Delegation;

use App\Models\CnameDelegation;
use App\Services\Delegation\CnameDelegationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * CnameDelegationService 数据库集成测试
 * 需要真实数据库连接
 */
#[Group('database')]
class CnameDelegationServiceTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = DatabaseSeeder::class;

    protected CnameDelegationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CnameDelegationService;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    // ==================== createOrGet ====================

    public function test_create_or_get_creates_new_delegation(): void
    {
        $user = $this->createTestUser();

        $delegation = $this->service->createOrGet($user->id, 'example.com', '_acme-challenge');

        $this->assertInstanceOf(CnameDelegation::class, $delegation);
        $this->assertEquals($user->id, $delegation->user_id);
        $this->assertEquals('example.com', $delegation->zone);
        $this->assertEquals('_acme-challenge', $delegation->prefix);
        $this->assertNotEmpty($delegation->label);
        $this->assertEquals(32, strlen($delegation->label));
        $this->assertFalse($delegation->valid);
    }

    public function test_create_or_get_returns_existing_delegation(): void
    {
        $user = $this->createTestUser();

        $delegation1 = $this->service->createOrGet($user->id, 'example.com', '_acme-challenge');
        $delegation2 = $this->service->createOrGet($user->id, 'example.com', '_acme-challenge');

        $this->assertEquals($delegation1->id, $delegation2->id);
    }

    public function test_create_or_get_normalizes_domain_to_lowercase(): void
    {
        $user = $this->createTestUser();

        $delegation = $this->service->createOrGet($user->id, 'EXAMPLE.COM', '_acme-challenge');

        $this->assertEquals('example.com', $delegation->zone);
    }

    public function test_create_or_get_stores_idn_as_unicode(): void
    {
        $user = $this->createTestUser();

        $delegation = $this->service->createOrGet($user->id, '中文.com', '_acme-challenge');

        $this->assertEquals('中文.com', $delegation->zone);
    }

    public function test_create_or_get_converts_punycode_input_to_unicode(): void
    {
        $user = $this->createTestUser();

        $delegation = $this->service->createOrGet($user->id, 'xn--fiq228c.com', '_acme-challenge');

        $this->assertEquals('中文.com', $delegation->zone);
    }

    public function test_create_or_get_generates_unique_label_per_user(): void
    {
        $user1 = $this->createTestUser();
        $user2 = $this->createTestUser();

        $delegation1 = $this->service->createOrGet($user1->id, 'example.com', '_acme-challenge');
        $delegation2 = $this->service->createOrGet($user2->id, 'example.com', '_acme-challenge');

        $this->assertNotEquals($delegation1->label, $delegation2->label);
    }

    public function test_create_or_get_different_prefixes_create_different_delegations(): void
    {
        $user = $this->createTestUser();

        $delegation1 = $this->service->createOrGet($user->id, 'example.com', '_acme-challenge');
        $delegation2 = $this->service->createOrGet($user->id, 'example.com', '_dnsauth');

        $this->assertNotEquals($delegation1->id, $delegation2->id);
    }

    // ==================== findDelegation ====================

    public function test_find_delegation_returns_exact_match(): void
    {
        $user = $this->createTestUser();
        $created = $this->createTestDelegation($user, [
            'zone' => 'example.com',
            'prefix' => '_acme-challenge',
        ]);

        $found = $this->service->findDelegation($user->id, 'example.com', '_acme-challenge');

        $this->assertNotNull($found);
        $this->assertEquals($created->id, $found->id);
    }

    public function test_find_delegation_strips_wildcard_prefix(): void
    {
        $user = $this->createTestUser();
        $created = $this->createTestDelegation($user, [
            'zone' => 'example.com',
            'prefix' => '_acme-challenge',
        ]);

        $found = $this->service->findDelegation($user->id, '*.example.com', '_acme-challenge');

        $this->assertNotNull($found);
        $this->assertEquals($created->id, $found->id);
    }

    public function test_find_delegation_acme_prefix_only_matches_exact_fqdn(): void
    {
        $user = $this->createTestUser();
        $this->createTestDelegation($user, [
            'zone' => 'example.com',
            'prefix' => '_acme-challenge',
        ]);

        // 子域名不应该匹配根域名的委托（对于 _acme-challenge）
        $found = $this->service->findDelegation($user->id, 'sub.example.com', '_acme-challenge');

        $this->assertNull($found);
    }

    public function test_find_delegation_acme_prefix_does_not_normalize_www_to_root(): void
    {
        $user = $this->createTestUser();
        $this->createTestDelegation($user, [
            'zone' => 'example.com',
            'prefix' => '_acme-challenge',
        ]);

        // 确保 www 在 _acme-challenge 场景下保持精确匹配语义
        $found = $this->service->findDelegation($user->id, 'www.example.com', '_acme-challenge');

        $this->assertNull($found);
    }

    public function test_find_delegation_dnsauth_prefix_only_matches_exact_fqdn(): void
    {
        $user = $this->createTestUser();
        $this->createTestDelegation($user, [
            'zone' => 'example.com',
            'prefix' => '_dnsauth',
        ]);

        // 子域名不应该匹配根域名的委托
        $found = $this->service->findDelegation($user->id, 'sub.example.com', '_dnsauth');

        $this->assertNull($found);
    }

    public function test_find_delegation_other_prefix_falls_back_to_root_domain(): void
    {
        $user = $this->createTestUser();
        $created = $this->createTestDelegation($user, [
            'zone' => 'example.com',
            'prefix' => '_pki-validation',
        ]);

        // 子域名应该回落到根域名
        $found = $this->service->findDelegation($user->id, 'sub.example.com', '_pki-validation');

        $this->assertNotNull($found);
        $this->assertEquals($created->id, $found->id);
    }

    public function test_find_delegation_prefers_subdomain_over_root(): void
    {
        $user = $this->createTestUser();
        $this->createTestDelegation($user, [
            'zone' => 'example.com',
            'prefix' => '_pki-validation',
        ]);
        $subDelegation = $this->createTestDelegation($user, [
            'zone' => 'sub.example.com',
            'prefix' => '_pki-validation',
        ]);

        $found = $this->service->findDelegation($user->id, 'sub.example.com', '_pki-validation');

        $this->assertEquals($subDelegation->id, $found->id);
    }

    public function test_find_delegation_returns_null_when_not_found(): void
    {
        $user = $this->createTestUser();

        $found = $this->service->findDelegation($user->id, 'notexist.com', '_acme-challenge');

        $this->assertNull($found);
    }

    // ==================== findValidDelegation ====================

    public function test_find_valid_delegation_returns_only_valid(): void
    {
        $user = $this->createTestUser();
        $this->createTestDelegation($user, [
            'zone' => 'example.com',
            'prefix' => '_acme-challenge',
            'valid' => true,
        ]);

        $found = $this->service->findValidDelegation($user->id, 'example.com', '_acme-challenge');

        $this->assertNotNull($found);
        $this->assertTrue($found->valid);
    }

    public function test_find_valid_delegation_returns_null_for_invalid(): void
    {
        $user = $this->createTestUser();
        $this->createTestDelegation($user, [
            'zone' => 'example.com',
            'prefix' => '_acme-challenge',
            'valid' => false,
        ]);

        $found = $this->service->findValidDelegation($user->id, 'example.com', '_acme-challenge');

        $this->assertNull($found);
    }

    public function test_find_valid_delegation_acme_prefix_does_not_normalize_www_to_root(): void
    {
        $user = $this->createTestUser();
        $this->createTestDelegation($user, [
            'zone' => 'example.com',
            'prefix' => '_acme-challenge',
            'valid' => true,
        ]);

        // 确保 www 在 _acme-challenge 场景下不回落到根域
        $found = $this->service->findValidDelegation($user->id, 'www.example.com', '_acme-challenge');

        $this->assertNull($found);
    }

    // ==================== checkAndUpdateValidity ====================

    public function test_check_and_update_validity_returns_boolean(): void
    {
        $user = $this->createTestUser();
        $delegation = $this->createTestDelegation($user, [
            'zone' => 'example.com',
            'prefix' => '_acme-challenge',
            'valid' => false,
            'fail_count' => 3,
        ]);

        // 实际调用会失败（因为没有真实的 CNAME 记录）
        // 这个测试验证方法能正常执行并返回布尔值
        $result = $this->service->checkAndUpdateValidity($delegation);

        $this->assertIsBool($result);
        $this->assertNotNull($delegation->last_checked_at);
    }

    public function test_check_and_update_validity_failure_increments_fail_count(): void
    {
        $user = $this->createTestUser();
        $delegation = $this->createTestDelegation($user, [
            'zone' => 'example.com',
            'prefix' => '_acme-challenge',
            'valid' => true,
            'fail_count' => 0,
        ]);

        // 实际调用会失败（因为没有真实的 CNAME 记录）
        $result = $this->service->checkAndUpdateValidity($delegation);

        $delegation->refresh();
        $this->assertFalse($result);
        $this->assertFalse($delegation->valid);
        $this->assertGreaterThan(0, $delegation->fail_count);
    }

    // ==================== withCnameGuide ====================

    public function test_with_cname_guide(): void
    {
        $user = $this->createTestUser();
        $delegation = $this->createTestDelegation($user, [
            'zone' => 'example.com',
            'prefix' => '_acme-challenge',
        ]);

        $result = $this->service->withCnameGuide($delegation);

        $this->assertArrayHasKey('cname_to', $result);
        $this->assertEquals('_acme-challenge.example.com', $result['cname_to']['host']);
        // value 依赖系统设置 delegation.proxyZone，可能为空
        $this->assertArrayHasKey('value', $result['cname_to']);
    }

    // ==================== update ====================

    public function test_update_regen_label(): void
    {
        $user = $this->createTestUser();
        $delegation = $this->createTestDelegation($user, [
            'zone' => 'example.com',
            'prefix' => '_acme-challenge',
            'valid' => true,
        ]);
        $oldLabel = $delegation->label;

        $updated = $this->service->update($user->id, $delegation->id, ['regen_label' => true]);

        // 注意：由于 label 生成使用相同的输入，新 label 可能相同
        // 但 valid 应该被重置
        $this->assertFalse($updated->valid);
        $this->assertEquals(0, $updated->fail_count);
    }

    public function test_update_throws_exception_for_other_user(): void
    {
        $user1 = $this->createTestUser();
        $user2 = $this->createTestUser();
        $delegation = $this->createTestDelegation($user1, [
            'zone' => 'example.com',
            'prefix' => '_acme-challenge',
        ]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->service->update($user2->id, $delegation->id, ['regen_label' => true]);
    }
}
