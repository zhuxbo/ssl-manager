<?php

namespace Tests\Unit\Services\Acme;

use App\Models\Acme\AcmeAuthorization;
use App\Models\Cert;
use App\Models\Order;
use App\Models\ProductPrice;
use App\Models\Transaction;
use App\Services\Acme\AcmeApiClient;
use App\Services\Acme\AcmeApiService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * AcmeApiService 测试（需要数据库）
 */
#[Group('database')]
class AcmeApiServiceTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = DatabaseSeeder::class;

    private AcmeApiService $service;

    private $mockApiClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockApiClient = Mockery::mock(AcmeApiClient::class);
        $this->mockApiClient->shouldReceive('isConfigured')->andReturn(false)->byDefault();
        $this->mockApiClient->shouldReceive('createAccount')->andReturn(['code' => 1])->byDefault();

        $this->app->instance(AcmeApiClient::class, $this->mockApiClient);
        $this->service = app(AcmeApiService::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_create_account_creates_order_and_cert_with_transaction(): void
    {
        $user = $this->createTestUser(['balance' => '500.00', 'email' => 'acme_test@example.com']);
        $product = $this->createTestProduct([
            'support_acme' => 1,
            'api_id' => 12345,
            'standard_min' => 1,
            'wildcard_min' => 0,
            'total_min' => 1,
        ]);
        $this->createProductPrice($product->id, $user);

        $result = $this->service->createAccount('acme_test@example.com', 12345);

        $this->assertEquals(1, $result['code']);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('eab_kid', $result['data']);
        $this->assertArrayHasKey('eab_hmac', $result['data']);

        // 验证 Order 创建
        $order = Order::where('user_id', $user->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertNotNull($order->eab_kid);
        $this->assertNotNull($order->eab_hmac);

        // 验证 Cert 创建
        $cert = Cert::where('order_id', $order->id)->first();
        $this->assertNotNull($cert);
        $this->assertEquals('acme', $cert->channel);
        $this->assertEquals('pending', $cert->status);

        // 验证 Transaction 创建
        $transaction = Transaction::where('transaction_id', $order->id)
            ->where('type', 'order')
            ->first();
        $this->assertNotNull($transaction);
    }

    public function test_create_account_generates_valid_eab_credentials(): void
    {
        $user = $this->createTestUser(['balance' => '500.00', 'email' => 'eab_test@example.com']);
        $product = $this->createTestProduct([
            'support_acme' => 1,
            'api_id' => 67890,
        ]);
        $this->createProductPrice($product->id, $user);

        $result = $this->service->createAccount('eab_test@example.com', 67890);

        $this->assertEquals(1, $result['code']);

        // eab_kid 是 UUID
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $result['data']['eab_kid']
        );

        // eab_hmac 是 base64url 编码
        $this->assertMatchesRegularExpression(
            '/^[A-Za-z0-9_-]+$/',
            $result['data']['eab_hmac']
        );
    }

    public function test_create_account_fails_when_user_not_found(): void
    {
        $product = $this->createTestProduct([
            'support_acme' => 1,
            'api_id' => 11111,
        ]);

        $result = $this->service->createAccount('nonexistent@example.com', 11111);

        $this->assertEquals(0, $result['code']);
        $this->assertStringContainsString('User not found', $result['msg']);
    }

    public function test_create_account_fails_when_product_not_found(): void
    {
        $this->createTestUser(['email' => 'product_test@example.com']);

        $result = $this->service->createAccount('product_test@example.com', 99999);

        $this->assertEquals(0, $result['code']);
        $this->assertStringContainsString('Product not found', $result['msg']);
    }

    public function test_create_account_reuses_existing_order_with_unused_eab(): void
    {
        $user = $this->createTestUser(['balance' => '500.00', 'email' => 'reuse_eab@example.com']);
        $product = $this->createTestProduct([
            'support_acme' => 1,
            'api_id' => 33333,
        ]);
        $this->createProductPrice($product->id, $user);

        // 先创建一次（生成 Order + EAB）
        $result1 = $this->service->createAccount('reuse_eab@example.com', 33333);
        $this->assertEquals(1, $result1['code']);

        $initialBalance = $user->fresh()->balance;

        // 再次调用应返回同一 EAB，不扣费
        $result2 = $this->service->createAccount('reuse_eab@example.com', 33333);
        $this->assertEquals(1, $result2['code']);
        $this->assertEquals($result1['data']['eab_kid'], $result2['data']['eab_kid']);
        $this->assertEquals($result1['data']['eab_hmac'], $result2['data']['eab_hmac']);

        // 余额不变
        $this->assertEquals($initialBalance, $user->fresh()->balance);
    }

    public function test_create_account_reuses_existing_eab_even_when_used(): void
    {
        $user = $this->createTestUser(['balance' => '500.00', 'email' => 'recover_eab@example.com']);
        $product = $this->createTestProduct([
            'support_acme' => 1,
            'api_id' => 44444,
        ]);
        $this->createProductPrice($product->id, $user);

        // 创建 Order + EAB
        $result1 = $this->service->createAccount('recover_eab@example.com', 44444);
        $this->assertEquals(1, $result1['code']);

        // 模拟 EAB 已使用
        $order = Order::find($result1['data']['id']);
        $order->eab_used_at = now();
        $order->save();

        $initialBalance = $user->fresh()->balance;

        // 再次调用应返回同一 EAB（EAB 可复用），不扣费
        $result2 = $this->service->createAccount('recover_eab@example.com', 44444);
        $this->assertEquals(1, $result2['code']);
        $this->assertEquals($result1['data']['eab_kid'], $result2['data']['eab_kid']);
        $this->assertEquals($result1['data']['eab_hmac'], $result2['data']['eab_hmac']);
        $this->assertEquals($result1['data']['id'], $result2['data']['id']);

        // 余额不变
        $this->assertEquals($initialBalance, $user->fresh()->balance);
    }

    public function test_create_account_fails_when_insufficient_balance(): void
    {
        $user = $this->createTestUser(['balance' => '0.00', 'email' => 'broke@example.com']);
        $product = $this->createTestProduct([
            'support_acme' => 1,
            'api_id' => 22222,
        ]);
        $this->createProductPrice($product->id, $user, '999.00');

        $result = $this->service->createAccount('broke@example.com', 22222);

        $this->assertEquals(0, $result['code']);
        $this->assertStringContainsString('balance', strtolower($result['msg']));
    }

    public function test_create_order_with_upstream_maps_ids(): void
    {
        $user = $this->createTestUser(['balance' => '500.00', 'email' => 'upstream_order@example.com']);
        $product = $this->createTestProduct([
            'support_acme' => 1,
            'api_id' => 55555,
            'standard_min' => 1,
            'wildcard_min' => 0,
            'total_min' => 1,
        ]);
        $order = $this->createTestOrder($user, $product, [
            'acme_account_id' => 42,
            'purchased_standard_count' => 10,
            'purchased_wildcard_count' => 10,
        ]);
        $cert = $this->createTestCert($order, [
            'channel' => 'acme',
            'status' => 'pending',
        ]);
        $cert->update(['api_id' => null, 'cert' => null]);

        // 上游应收到 order.acme_account_id (42) 和 product.api_id (55555)
        $this->mockApiClient->shouldReceive('isConfigured')->andReturn(true);
        $this->mockApiClient->shouldReceive('createOrder')
            ->once()
            ->with(42, ['example.com'], '55555')
            ->andReturn([
                'code' => 1,
                'data' => [
                    'id' => 777,
                    'authorizations' => [
                        [
                            'identifier' => ['type' => 'dns', 'value' => 'example.com'],
                            'status' => 'pending',
                            'challenges' => [
                                [
                                    'id' => 888,
                                    'type' => 'dns-01',
                                    'token' => 'test-token',
                                    'key_authorization' => 'test-key-auth',
                                    'status' => 'pending',
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $result = $this->service->createOrder($order->id, ['example.com'], '55555');

        $this->assertEquals(1, $result['code']);
        // 返回本级 cert.id，不是上游 777
        $this->assertNotEquals(777, $result['data']['id']);

        // cert.api_id 存储了上游 ID
        $updatedCert = Cert::find($result['data']['id']);
        $this->assertEquals(777, $updatedCert->api_id);

        // AcmeAuthorization.acme_challenge_id 存储了上游 challenge ID
        $authorization = $updatedCert->acmeAuthorizations->first();
        $this->assertNotNull($authorization);
        $this->assertEquals(888, $authorization->acme_challenge_id);
    }

    public function test_respond_to_challenge_with_upstream_maps_challenge_id(): void
    {
        $user = $this->createTestUser(['email' => 'challenge_map@example.com']);
        $product = $this->createTestProduct(['support_acme' => 1, 'api_id' => 12345]);
        $order = $this->createTestOrder($user, $product);
        $cert = $this->createTestCert($order, ['channel' => 'acme']);

        $authorization = AcmeAuthorization::create([
            'cert_id' => $cert->id,
            'token' => 'test-token-map',
            'identifier_type' => 'dns',
            'identifier_value' => 'example.com',
            'wildcard' => false,
            'status' => 'pending',
            'expires' => now()->addDays(7),
            'challenge_type' => 'dns-01',
            'challenge_token' => 'chall-token',
            'acme_challenge_id' => 555,
            'key_authorization' => 'key-auth',
            'challenge_status' => 'pending',
        ]);

        // 上游应收到 acme_challenge_id (555)，不是 authorization.id
        $this->mockApiClient->shouldReceive('isConfigured')->andReturn(true);
        $this->mockApiClient->shouldReceive('respondToChallenge')
            ->once()
            ->with(555)
            ->andReturn(['code' => 1, 'data' => ['status' => 'valid']]);

        $result = $this->service->respondToChallenge($authorization->id);

        $this->assertEquals(1, $result['code']);
        $this->assertEquals('valid', $result['data']['status']);

        $authorization->refresh();
        $this->assertEquals('valid', $authorization->status);
    }

    public function test_finalize_order_with_upstream_maps_cert_api_id(): void
    {
        $user = $this->createTestUser(['email' => 'finalize_map@example.com']);
        $product = $this->createTestProduct(['support_acme' => 1, 'api_id' => 12345]);
        $order = $this->createTestOrder($user, $product);
        $cert = $this->createTestCert($order, [
            'channel' => 'acme',
            'status' => 'pending',
        ]);
        $cert->update(['api_id' => 999, 'csr' => null, 'cert' => null]);

        // 创建已验证的授权（ACME 状态为 ready）
        AcmeAuthorization::create([
            'cert_id' => $cert->id,
            'token' => 'finalize-token',
            'identifier_type' => 'dns',
            'identifier_value' => 'example.com',
            'wildcard' => false,
            'status' => 'valid',
            'expires' => now()->addDays(7),
            'challenge_type' => 'dns-01',
            'challenge_token' => 'chall-token',
            'acme_challenge_id' => 555,
            'key_authorization' => 'key-auth',
            'challenge_status' => 'valid',
            'challenge_validated' => now(),
        ]);

        $selfSigned = $this->generateSelfSignedCert();

        // 上游应收到 cert.api_id (999)
        $this->mockApiClient->shouldReceive('isConfigured')->andReturn(true);
        $this->mockApiClient->shouldReceive('finalizeOrder')
            ->once()
            ->with(999, Mockery::type('string'))
            ->andReturn(['code' => 1, 'data' => []]);
        $this->mockApiClient->shouldReceive('getCertificate')
            ->once()
            ->with(999)
            ->andReturn(['code' => 1, 'data' => $selfSigned]);

        // base64url 编码的 CSR
        $csrPem = $this->generateTestCsr();
        $csrDer = $this->pemToDer($csrPem);
        $csrBase64url = rtrim(strtr(base64_encode($csrDer), '+/', '-_'), '=');

        $result = $this->service->finalizeOrder($cert->id, $csrBase64url);

        $this->assertEquals(1, $result['code']);
        $this->assertEquals($cert->id, $result['data']['id']);
    }

    public function test_format_order_always_returns_local_id(): void
    {
        $user = $this->createTestUser(['email' => 'format_id@example.com']);
        $product = $this->createTestProduct(['support_acme' => 1, 'api_id' => 12345]);
        $order = $this->createTestOrder($user, $product);
        $cert = $this->createTestCert($order, [
            'channel' => 'acme',
            'status' => 'pending',
        ]);
        $cert->update(['api_id' => 777]);

        // getOrder 内部调用 formatOrder，应返回 cert.id 而非 api_id
        $result = $this->service->getOrder($cert->id);

        $this->assertEquals(1, $result['code']);
        $this->assertEquals($cert->id, $result['data']['id']);
        $this->assertNotEquals(777, $result['data']['id']);
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

    private function generateSelfSignedCert(): array
    {
        $privateKey = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);
        $csr = openssl_csr_new(['commonName' => 'example.com'], $privateKey);
        $x509 = openssl_csr_sign($csr, null, $privateKey, 365);
        openssl_x509_export($x509, $certPem);

        return ['certificate' => $certPem, 'chain' => ''];
    }

    private function pemToDer(string $pem): string
    {
        $base64 = str_replace(
            ['-----BEGIN CERTIFICATE REQUEST-----', '-----END CERTIFICATE REQUEST-----', "\n", "\r"],
            '',
            $pem
        );

        return base64_decode($base64);
    }
}
