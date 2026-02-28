<?php

use App\Models\Acme\Authorization;
use App\Models\Cert;
use App\Models\Order;
use App\Models\ProductPrice;
use App\Services\Acme\ApiClient;
use App\Services\Acme\ApiService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Traits\CreatesTestData;

uses(Tests\TestCase::class, CreatesTestData::class, RefreshDatabase::class)->group('database');

beforeEach(function () {
    $this->seed = true;
    $this->seeder = DatabaseSeeder::class;

    $this->mockApiClient = Mockery::mock(ApiClient::class);
    $this->mockApiClient->shouldReceive('isConfigured')->andReturn(false)->byDefault();

    $this->app->instance(ApiClient::class, $this->mockApiClient);
    $this->service = app(ApiService::class);
});

afterEach(function () {
    Mockery::close();
});

/**
 * 创建产品价格（ApiService 测试用）
 */
function createApiServiceProductPrice(int $productId, $user, string $price = '100.00'): void
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

test('create order creates order and cert', function () {
    $user = $this->createTestUser(['balance' => '500.00', 'email' => 'acme_test@example.com']);
    $product = $this->createTestProduct([
        'support_acme' => 1,
        'api_id' => 12345,
        'standard_min' => 1,
        'wildcard_min' => 0,
        'total_min' => 1,
    ]);
    createApiServiceProductPrice($product->id, $user);

    // 配置上游
    $this->mockApiClient->shouldReceive('isConfigured')->andReturn(true);
    $this->mockApiClient->shouldReceive('createOrder')
        ->once()
        ->withArgs(function ($customer, $productCode, $domains, $referId) {
            return $customer === 'acme_test@example.com'
                && $productCode === '12345'
                && $domains === ['example.com'];
        })
        ->andReturn([
            'code' => 1,
            'data' => [
                'id' => 777,
                'authorizations' => [
                    [
                        'identifier' => ['type' => 'dns', 'value' => 'example.com'],
                        'status' => 'pending',
                        'challenges' => [
                            ['id' => 888, 'type' => 'dns-01', 'token' => 'test-token', 'key_authorization' => 'test-key-auth', 'status' => 'pending'],
                        ],
                    ],
                ],
            ],
        ]);

    $result = $this->service->createOrder('acme_test@example.com', '12345', ['example.com']);

    expect($result['code'])->toBe(1);
    expect($result)->toHaveKey('data');

    // 验证 Order 创建
    $order = Order::where('user_id', $user->id)->latest()->first();
    expect($order)->not->toBeNull();

    // 验证 Cert 创建
    $cert = Cert::where('order_id', $order->id)->where('channel', 'acme')->latest()->first();
    expect($cert)->not->toBeNull();
    expect($cert->channel)->toBe('acme');
});

test('create order fails when user not found', function () {
    $result = $this->service->createOrder('nonexistent@example.com', '11111', ['example.com']);

    expect($result['code'])->toBe(0);
    expect($result['msg'])->toContain('User not found');
});

test('create order fails when product not found', function () {
    $this->createTestUser(['email' => 'product_test@example.com']);

    $result = $this->service->createOrder('product_test@example.com', '99999', ['example.com']);

    expect($result['code'])->toBe(0);
    expect($result['msg'])->toContain('Product not found');
});

test('create order reuses existing order', function () {
    $user = $this->createTestUser(['balance' => '500.00', 'email' => 'reuse@example.com']);
    $product = $this->createTestProduct([
        'support_acme' => 1,
        'api_id' => 33333,
        'standard_min' => 1,
        'wildcard_min' => 0,
        'total_min' => 1,
    ]);
    createApiServiceProductPrice($product->id, $user);

    // 创建已有 Order
    $existingOrder = $this->createTestOrder($user, $product, [
        'period_till' => now()->addYear(),
        'purchased_standard_count' => 1,
        'purchased_wildcard_count' => 0,
    ]);
    $existingCert = $this->createTestCert($existingOrder, ['channel' => 'acme']);

    $this->mockApiClient->shouldReceive('isConfigured')->andReturn(true);
    $this->mockApiClient->shouldReceive('createOrder')
        ->once()
        ->andReturn([
            'code' => 1,
            'data' => [
                'id' => 777,
                'authorizations' => [
                    [
                        'identifier' => ['type' => 'dns', 'value' => 'example.com'],
                        'status' => 'pending',
                        'challenges' => [
                            ['id' => 888, 'type' => 'dns-01', 'token' => 'test-token', 'key_authorization' => 'ka', 'status' => 'pending'],
                        ],
                    ],
                ],
            ],
        ]);

    $result = $this->service->createOrder('reuse@example.com', '33333', ['example.com']);
    expect($result['code'])->toBe(1);

    // 应该复用已有 Order，不创建新的
    $orderCount = Order::where('user_id', $user->id)->where('product_id', $product->id)->count();
    expect($orderCount)->toBe(1);
});

test('create order with upstream maps ids', function () {
    $user = $this->createTestUser(['balance' => '500.00', 'email' => 'upstream_order@example.com']);
    $product = $this->createTestProduct([
        'support_acme' => 1,
        'api_id' => 55555,
        'standard_min' => 1,
        'wildcard_min' => 0,
        'total_min' => 1,
    ]);
    $order = $this->createTestOrder($user, $product, [
        'purchased_standard_count' => 10,
        'purchased_wildcard_count' => 10,
    ]);
    $cert = $this->createTestCert($order, [
        'channel' => 'acme',
        'status' => 'pending',
    ]);
    $cert->update(['api_id' => null, 'cert' => null]);

    // 上游应收到 email + product.api_id + domains
    $this->mockApiClient->shouldReceive('isConfigured')->andReturn(true);
    $this->mockApiClient->shouldReceive('createOrder')
        ->once()
        ->withArgs(function ($customer, $productCode, $domains, $referId) {
            return $customer === 'upstream_order@example.com'
                && $productCode === '55555'
                && $domains === ['example.com'];
        })
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

    $result = $this->service->createOrder('upstream_order@example.com', '55555', ['example.com']);

    expect($result['code'])->toBe(1);
    // 返回本级 order.id，不是上游 777
    expect($result['data']['id'])->not->toBe(777);

    // cert.api_id 存储了上游 ID
    $updatedOrder = Order::find($result['data']['id']);
    $updatedCert = Cert::find($updatedOrder->latest_cert_id);
    expect((int) $updatedCert->api_id)->toBe(777);

    // AcmeAuthorization.acme_challenge_id 存储了上游 challenge ID
    $authorization = $updatedCert->acmeAuthorizations->first();
    expect($authorization)->not->toBeNull();
    expect((int) $authorization->acme_challenge_id)->toBe(888);
});

test('reissue order calls upstream reissue', function () {
    $user = $this->createTestUser(['balance' => '500.00', 'email' => 'reissue@example.com']);
    $product = $this->createTestProduct([
        'support_acme' => 1,
        'api_id' => 66666,
        'standard_min' => 1,
        'wildcard_min' => 0,
        'total_min' => 1,
        'reissue' => 1,
    ]);
    createApiServiceProductPrice($product->id, $user);

    $order = $this->createTestOrder($user, $product, [
        'period_till' => now()->addYear(),
        'purchased_standard_count' => 3,
        'purchased_wildcard_count' => 0,
    ]);
    // 已签发的 cert（有 api_id）
    $cert = $this->createTestCert($order, [
        'channel' => 'acme',
        'status' => 'active',
    ]);
    $cert->update(['api_id' => 999]);

    $this->mockApiClient->shouldReceive('isConfigured')->andReturn(true);
    $this->mockApiClient->shouldReceive('reissueOrder')
        ->once()
        ->withArgs(function ($orderId, $domains, $referId) {
            return $orderId === 999
                && $domains === ['new.example.com'];
        })
        ->andReturn([
            'code' => 1,
            'data' => [
                'id' => 1001,
                'authorizations' => [
                    [
                        'identifier' => ['type' => 'dns', 'value' => 'new.example.com'],
                        'status' => 'pending',
                        'challenges' => [
                            ['id' => 2001, 'type' => 'dns-01', 'token' => 'reissue-token', 'key_authorization' => 'ka', 'status' => 'pending'],
                        ],
                    ],
                ],
            ],
        ]);

    $result = $this->service->reissueOrder($order->id, ['new.example.com']);

    expect($result['code'])->toBe(1);
    expect($result['data']['id'])->toBe($order->id);
});

test('reissue order fails when no upstream id', function () {
    $user = $this->createTestUser(['balance' => '500.00', 'email' => 'noid@example.com']);
    $product = $this->createTestProduct([
        'support_acme' => 1,
        'api_id' => 77777,
        'reissue' => 1,
    ]);
    createApiServiceProductPrice($product->id, $user);

    $order = $this->createTestOrder($user, $product, [
        'period_till' => now()->addYear(),
        'purchased_standard_count' => 1,
    ]);
    // cert 没有 api_id
    $cert = $this->createTestCert($order, [
        'channel' => 'acme',
        'status' => 'pending',
    ]);
    $cert->update(['api_id' => null]);

    $this->mockApiClient->shouldReceive('isConfigured')->andReturn(true);

    $result = $this->service->reissueOrder($order->id, ['example.com']);

    expect($result['code'])->toBe(0);
    expect($result['msg'])->toContain('No upstream order ID');
});

test('respond to challenge with upstream maps challenge id', function () {
    $user = $this->createTestUser(['email' => 'challenge_map@example.com']);
    $product = $this->createTestProduct(['support_acme' => 1, 'api_id' => 12345]);
    $order = $this->createTestOrder($user, $product);
    $cert = $this->createTestCert($order, ['channel' => 'acme']);

    $authorization = Authorization::create([
        'cert_id' => $cert->id,
        'token' => 'test-token-map',
        'identifier_type' => 'dns',
        'identifier_value' => 'example.com',
        'wildcard' => false,
        'status' => 'pending',
        'expires_at' => now()->addDays(7),
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

    expect($result['code'])->toBe(1);
    expect($result['data']['status'])->toBe('valid');

    $authorization->refresh();
    expect($authorization->status)->toBe('valid');
});

test('finalize order with upstream maps cert api id', function () {
    $user = $this->createTestUser(['email' => 'finalize_map@example.com']);
    $product = $this->createTestProduct(['support_acme' => 1, 'api_id' => 12345]);
    $order = $this->createTestOrder($user, $product);
    $cert = $this->createTestCert($order, [
        'channel' => 'acme',
        'status' => 'pending',
    ]);
    $cert->update(['api_id' => 999, 'csr' => null, 'cert' => null]);

    // 创建已验证的授权（ACME 状态为 ready）
    Authorization::create([
        'cert_id' => $cert->id,
        'token' => 'finalize-token',
        'identifier_type' => 'dns',
        'identifier_value' => 'example.com',
        'wildcard' => false,
        'status' => 'valid',
        'expires_at' => now()->addDays(7),
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

    $result = $this->service->finalizeOrder($order->id, $csrBase64url);

    expect($result['code'])->toBe(1);
    expect($result['data']['id'])->toBe($order->id);
});

test('format order always returns local id', function () {
    $user = $this->createTestUser(['email' => 'format_id@example.com']);
    $product = $this->createTestProduct(['support_acme' => 1, 'api_id' => 12345]);
    $order = $this->createTestOrder($user, $product);
    $cert = $this->createTestCert($order, [
        'channel' => 'acme',
        'status' => 'pending',
    ]);
    $cert->update(['api_id' => 777]);

    // getOrder 内部调用 formatOrder，应返回 order.id 而非 api_id
    $result = $this->service->getOrder($order->id);

    expect($result['code'])->toBe(1);
    expect($result['data']['id'])->toBe($order->id);
    expect($result['data']['id'])->not->toBe(777);
});
