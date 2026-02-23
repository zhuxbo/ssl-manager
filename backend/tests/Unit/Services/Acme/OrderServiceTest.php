<?php

namespace Tests\Unit\Services\Acme;

use App\Models\Acme\AcmeAccount;
use App\Models\Acme\AcmeAuthorization;
use App\Models\Cert;
use App\Models\ProductPrice;
use App\Services\Acme\AcmeApiClient;
use App\Services\Acme\BillingService;
use App\Services\Acme\OrderService;
use App\Services\Delegation\CnameDelegationService;
use App\Services\Delegation\DelegationDnsService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * OrderService 测试（需要数据库）
 */
#[Group('database')]
class OrderServiceTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = DatabaseSeeder::class;

    private OrderService $service;

    private $mockApiClient;

    private $mockCnameDelegationService;

    private $mockDelegationDnsService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockApiClient = Mockery::mock(AcmeApiClient::class);
        $this->mockApiClient->shouldReceive('isConfigured')->andReturn(true)->byDefault();

        $this->mockCnameDelegationService = Mockery::mock(CnameDelegationService::class);
        $this->mockCnameDelegationService->shouldReceive('findValidDelegation')->andReturn(null)->byDefault();

        $this->mockDelegationDnsService = Mockery::mock(DelegationDnsService::class);

        $billingService = app(BillingService::class);
        $this->service = new OrderService(
            $billingService,
            $this->mockApiClient,
            $this->mockCnameDelegationService,
            $this->mockDelegationDnsService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_create_validates_san_count_against_product_limits(): void
    {
        $user = $this->createTestUser(['balance' => '1000.00']);
        $product = $this->createTestProduct([
            'support_acme' => 1,
            'standard_max' => 2,
            'wildcard_max' => 0,
            'total_max' => 2,
        ]);
        $this->createProductPrice($product->id, $user);

        $order = $this->createTestOrder($user, $product, [
            'period_till' => now()->addYear(),
        ]);
        $cert = $this->createTestCert($order, ['channel' => 'acme']);

        $account = AcmeAccount::create([
            'user_id' => $user->id,
            'key_id' => 'test_key_'.uniqid(),
            'public_key' => ['kty' => 'RSA'],
            'status' => 'valid',
        ]);

        // 提交 3 个域名，超过 standard_max=2
        $identifiers = [
            ['type' => 'dns', 'value' => 'a.com'],
            ['type' => 'dns', 'value' => 'b.com'],
            ['type' => 'dns', 'value' => 'c.com'],
        ];

        $result = $this->service->create($account, $identifiers);

        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('rejectedIdentifier', $result['error']);
    }

    public function test_create_charges_additional_san_fee_when_exceeding_purchased(): void
    {
        $user = $this->createTestUser(['balance' => '1000.00']);
        $product = $this->createTestProduct([
            'support_acme' => 1,
            'standard_min' => 1,
            'standard_max' => 10,
            'total_min' => 1,
            'total_max' => 10,
        ]);
        $this->createProductPrice($product->id, $user);

        $order = $this->createTestOrder($user, $product, [
            'period_till' => now()->addYear(),
            'purchased_standard_count' => 1,
            'purchased_wildcard_count' => 0,
        ]);
        // 设置 api_id 使其不可复用，触发创建新的 reissue cert
        $cert = $this->createTestCert($order, [
            'channel' => 'acme',
            'standard_count' => 1,
            'api_id' => 100,
        ]);

        $account = AcmeAccount::create([
            'user_id' => $user->id,
            'key_id' => 'test_key_'.uniqid(),
            'public_key' => ['kty' => 'RSA'],
            'status' => 'valid',
        ]);

        // Mock API 返回成功
        $this->mockApiClient->shouldReceive('createOrder')->once()->andReturn([
            'code' => 1,
            'data' => [
                'id' => 999,
                'authorizations' => [
                    [
                        'identifier' => ['type' => 'dns', 'value' => 'a.com'],
                        'status' => 'pending',
                        'challenges' => [['id' => 1, 'type' => 'dns-01', 'token' => 'real-token-1', 'key_authorization' => 'ka-1', 'status' => 'pending']],
                    ],
                    [
                        'identifier' => ['type' => 'dns', 'value' => 'b.com'],
                        'status' => 'pending',
                        'challenges' => [['id' => 2, 'type' => 'dns-01', 'token' => 'real-token-2', 'key_authorization' => 'ka-2', 'status' => 'pending']],
                    ],
                    [
                        'identifier' => ['type' => 'dns', 'value' => 'c.com'],
                        'status' => 'pending',
                        'challenges' => [['id' => 3, 'type' => 'dns-01', 'token' => 'real-token-3', 'key_authorization' => 'ka-3', 'status' => 'pending']],
                    ],
                ],
            ],
        ]);

        // 提交 3 个域名（已购 1 个，增购 2 个）
        $identifiers = [
            ['type' => 'dns', 'value' => 'a.com'],
            ['type' => 'dns', 'value' => 'b.com'],
            ['type' => 'dns', 'value' => 'c.com'],
        ];

        $result = $this->service->create($account, $identifiers);

        $this->assertArrayHasKey('order', $result);

        // 验证创建了 reissue cert
        $newCert = Cert::where('order_id', $order->id)
            ->where('action', 'reissue')
            ->first();
        $this->assertNotNull($newCert);
        $this->assertEquals(3, $newCert->standard_count);
    }

    public function test_create_does_not_add_gift_root_domain(): void
    {
        $user = $this->createTestUser(['balance' => '1000.00']);
        $product = $this->createTestProduct([
            'support_acme' => 1,
            'gift_root_domain' => 1,
            'standard_min' => 1,
            'standard_max' => 10,
            'total_min' => 1,
            'total_max' => 10,
        ]);
        $this->createProductPrice($product->id, $user);

        $order = $this->createTestOrder($user, $product, [
            'period_till' => now()->addYear(),
            'purchased_standard_count' => 1,
        ]);
        $cert = $this->createTestCert($order, ['channel' => 'acme']);

        $account = AcmeAccount::create([
            'user_id' => $user->id,
            'key_id' => 'test_key_'.uniqid(),
            'public_key' => ['kty' => 'RSA'],
            'status' => 'valid',
        ]);

        $this->mockApiClient->shouldReceive('createOrder')->once()->andReturn([
            'code' => 1,
            'data' => [
                'id' => 888,
                'authorizations' => [
                    [
                        'identifier' => ['type' => 'dns', 'value' => 'www.example.com'],
                        'status' => 'pending',
                        'challenges' => [['id' => 10, 'type' => 'dns-01', 'token' => 'tok', 'key_authorization' => 'ka', 'status' => 'pending']],
                    ],
                ],
            ],
        ]);

        // ACME 提交 www.example.com，不应自动补齐 example.com
        $identifiers = [['type' => 'dns', 'value' => 'www.example.com']];

        $result = $this->service->create($account, $identifiers);

        $this->assertArrayHasKey('order', $result);

        // 验证 alternative_names 只有 www.example.com
        $resultCert = $result['order'];
        $this->assertEquals('www.example.com', $resultCert->alternative_names);
    }

    public function test_create_stores_api_id_in_cert(): void
    {
        $user = $this->createTestUser(['balance' => '1000.00']);
        $product = $this->createTestProduct(['support_acme' => 1]);
        $this->createProductPrice($product->id, $user);

        $order = $this->createTestOrder($user, $product, [
            'period_till' => now()->addYear(),
        ]);
        $cert = $this->createTestCert($order, ['channel' => 'acme']);

        $account = AcmeAccount::create([
            'user_id' => $user->id,
            'key_id' => 'test_key_'.uniqid(),
            'public_key' => ['kty' => 'RSA'],
            'status' => 'valid',
        ]);

        $this->mockApiClient->shouldReceive('createOrder')->once()->andReturn([
            'code' => 1,
            'data' => [
                'id' => 12345,
                'authorizations' => [
                    [
                        'identifier' => ['type' => 'dns', 'value' => 'test.com'],
                        'status' => 'pending',
                        'challenges' => [['id' => 5, 'type' => 'dns-01', 'token' => 'tk', 'key_authorization' => 'ka', 'status' => 'pending']],
                    ],
                ],
            ],
        ]);

        $identifiers = [['type' => 'dns', 'value' => 'test.com']];
        $result = $this->service->create($account, $identifiers);

        $this->assertArrayHasKey('order', $result);
        $this->assertEquals(12345, $result['order']->api_id);
    }

    public function test_get_acme_status_derives_correctly(): void
    {
        $user = $this->createTestUser();
        $product = $this->createTestProduct(['support_acme' => 1]);
        $order = $this->createTestOrder($user, $product);

        // 状态 revoked → invalid
        $cert1 = $this->createTestCert($order, ['channel' => 'acme', 'status' => 'revoked']);
        $this->assertEquals('invalid', $this->service->getAcmeStatus($cert1));

        // 有证书内容 → valid
        $cert2 = Cert::create([
            'order_id' => $order->id,
            'action' => 'new',
            'channel' => 'acme',
            'common_name' => 'test.com',
            'status' => 'active',
            'cert' => '-----BEGIN CERTIFICATE-----\ntest\n-----END CERTIFICATE-----',
            'csr' => $this->generateTestCsr(),
        ]);
        $this->assertEquals('valid', $this->service->getAcmeStatus($cert2));

        // 有 CSR 无证书 → processing
        $cert3 = Cert::create([
            'order_id' => $order->id,
            'action' => 'new',
            'channel' => 'acme',
            'common_name' => 'test.com',
            'status' => 'pending',
            'csr' => $this->generateTestCsr(),
        ]);
        $this->assertEquals('processing', $this->service->getAcmeStatus($cert3));

        // 所有授权 valid → ready
        $cert4 = Cert::create([
            'order_id' => $order->id,
            'action' => 'new',
            'channel' => 'acme',
            'common_name' => 'test.com',
            'status' => 'pending',
        ]);
        AcmeAuthorization::create([
            'cert_id' => $cert4->id,
            'token' => 'auth_token_'.uniqid(),
            'identifier_type' => 'dns',
            'identifier_value' => 'test.com',
            'status' => 'valid',
            'challenge_type' => 'dns-01',
            'challenge_status' => 'valid',
        ]);
        $this->assertEquals('ready', $this->service->getAcmeStatus($cert4));

        // 有授权但未全部 valid → pending
        $cert5 = Cert::create([
            'order_id' => $order->id,
            'action' => 'new',
            'channel' => 'acme',
            'common_name' => 'test.com',
            'status' => 'pending',
        ]);
        AcmeAuthorization::create([
            'cert_id' => $cert5->id,
            'token' => 'auth_token_'.uniqid(),
            'identifier_type' => 'dns',
            'identifier_value' => 'a.com',
            'status' => 'valid',
            'challenge_type' => 'dns-01',
            'challenge_status' => 'valid',
        ]);
        AcmeAuthorization::create([
            'cert_id' => $cert5->id,
            'token' => 'auth_token_'.uniqid(),
            'identifier_type' => 'dns',
            'identifier_value' => 'b.com',
            'status' => 'pending',
            'challenge_type' => 'dns-01',
            'challenge_status' => 'pending',
        ]);
        $this->assertEquals('pending', $this->service->getAcmeStatus($cert5));
    }

    public function test_get_finds_cert_by_refer_id(): void
    {
        $user = $this->createTestUser();
        $product = $this->createTestProduct(['support_acme' => 1]);
        $order = $this->createTestOrder($user, $product);
        $cert = $this->createTestCert($order, [
            'channel' => 'acme',
            'refer_id' => 'test_refer_id_12345678901234',
        ]);

        $found = $this->service->get('test_refer_id_12345678901234');

        $this->assertNotNull($found);
        $this->assertEquals($cert->id, $found->id);
    }

    public function test_create_prefers_order_id_over_user_id_lookup(): void
    {
        $user = $this->createTestUser(['balance' => '1000.00']);
        $product = $this->createTestProduct([
            'support_acme' => 1,
            'standard_min' => 1,
            'standard_max' => 10,
            'total_min' => 1,
            'total_max' => 10,
        ]);
        $this->createProductPrice($product->id, $user);

        // 创建两个有效 Order
        $order1 = $this->createTestOrder($user, $product, [
            'period_till' => now()->addYear(),
            'purchased_standard_count' => 1,
        ]);
        $cert1 = $this->createTestCert($order1, ['channel' => 'acme']);

        $order2 = $this->createTestOrder($user, $product, [
            'period_till' => now()->addMonths(6),
            'purchased_standard_count' => 1,
        ]);
        $cert2 = $this->createTestCert($order2, ['channel' => 'acme']);

        // AcmeAccount 绑定 order2
        $account = AcmeAccount::create([
            'user_id' => $user->id,
            'order_id' => $order2->id,
            'key_id' => 'test_key_'.uniqid(),
            'public_key' => ['kty' => 'RSA'],
            'status' => 'valid',
        ]);

        $this->mockApiClient->shouldReceive('createOrder')->once()->andReturn([
            'code' => 1,
            'data' => [
                'id' => 111,
                'authorizations' => [
                    [
                        'identifier' => ['type' => 'dns', 'value' => 'test.com'],
                        'status' => 'pending',
                        'challenges' => [['id' => 1, 'type' => 'dns-01', 'token' => 'tk', 'key_authorization' => 'ka', 'status' => 'pending']],
                    ],
                ],
            ],
        ]);

        $identifiers = [['type' => 'dns', 'value' => 'test.com']];
        $result = $this->service->create($account, $identifiers);

        $this->assertArrayHasKey('order', $result);
        // 应该用 order2 而不是 order1（虽然 order1 有效期更长）
        $this->assertEquals($order2->id, $result['order']->order_id);
    }

    public function test_create_writes_delegation_txt_for_dns01_authorizations(): void
    {
        $user = $this->createTestUser(['balance' => '1000.00']);
        $product = $this->createTestProduct(['support_acme' => 1]);
        $this->createProductPrice($product->id, $user);

        $order = $this->createTestOrder($user, $product, [
            'period_till' => now()->addYear(),
            'purchased_standard_count' => 1,
        ]);
        $cert = $this->createTestCert($order, ['channel' => 'acme']);

        $account = AcmeAccount::create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'key_id' => 'test_key_'.uniqid(),
            'public_key' => ['kty' => 'RSA'],
            'status' => 'valid',
        ]);

        $delegation = $this->createTestDelegation($user, [
            'zone' => 'example.com',
            'prefix' => '_acme-challenge',
        ]);

        // 模拟找到委托
        $this->mockCnameDelegationService->shouldReceive('findValidDelegation')
            ->with($user->id, 'example.com', '_acme-challenge')
            ->andReturn($delegation);

        // 期望写入 TXT
        $this->mockDelegationDnsService->shouldReceive('setTxtByLabel')
            ->once()
            ->withArgs(function ($proxyZone, $label, $values) use ($delegation) {
                return $label === $delegation->label && count($values) === 1;
            })
            ->andReturn(true);

        $this->mockApiClient->shouldReceive('createOrder')->once()->andReturn([
            'code' => 1,
            'data' => [
                'id' => 222,
                'authorizations' => [
                    [
                        'identifier' => ['type' => 'dns', 'value' => 'example.com'],
                        'status' => 'pending',
                        'challenges' => [['id' => 1, 'type' => 'dns-01', 'token' => 'tk', 'key_authorization' => 'test-key-auth', 'status' => 'pending']],
                    ],
                ],
            ],
        ]);

        $identifiers = [['type' => 'dns', 'value' => 'example.com']];
        $result = $this->service->create($account, $identifiers);

        $this->assertArrayHasKey('order', $result);
    }

    public function test_get_returns_null_for_non_acme_cert(): void
    {
        $user = $this->createTestUser();
        $product = $this->createTestProduct();
        $order = $this->createTestOrder($user, $product);
        $cert = $this->createTestCert($order, [
            'channel' => 'api',
            'refer_id' => 'non_acme_refer_id_1234567890',
        ]);

        $found = $this->service->get('non_acme_refer_id_1234567890');

        $this->assertNull($found);
    }

    /**
     * 创建产品价格
     */
    private function createProductPrice(int $productId, $user): void
    {
        ProductPrice::create([
            'product_id' => $productId,
            'level_code' => $user->level_code ?? 'standard',
            'period' => 12,
            'price' => '100.00',
            'alternative_standard_price' => '10.00',
            'alternative_wildcard_price' => '20.00',
        ]);
    }
}
