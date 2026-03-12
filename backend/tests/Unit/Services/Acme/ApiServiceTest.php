<?php

use App\Models\Acme\AcmeCert;
use App\Models\Acme\AcmeOrder;
use App\Models\Acme\Authorization;
use App\Models\ProductPrice;
use App\Services\Acme\Api\AcmeSourceApiInterface;
use App\Services\Acme\Api\Api as AcmeApiFactory;
use App\Services\Acme\ApiService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Traits\CreatesTestData;

uses(Tests\TestCase::class, CreatesTestData::class, RefreshDatabase::class)->group('database');

beforeEach(function () {
    $this->seed = true;
    $this->seeder = DatabaseSeeder::class;

    $this->mockSourceApi = Mockery::mock(AcmeSourceApiInterface::class);
    $this->mockSourceApi->shouldReceive('isConfigured')->andReturn(false)->byDefault();

    $this->mockSourceApiFactory = Mockery::mock(AcmeApiFactory::class);
    $this->mockSourceApiFactory->shouldReceive('getSourceApi')->andReturn($this->mockSourceApi)->byDefault();

    $this->app->instance(AcmeApiFactory::class, $this->mockSourceApiFactory);
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

// ==================== prepareOrder 测试 ====================

test('prepare order creates order and cert', function () {
    $user = $this->createTestUser(['balance' => '500.00', 'email' => 'acme_test@example.com']);
    $product = $this->createTestProduct([
        'product_type' => 'acme',
        'api_id' => 12345,
        'standard_min' => 1,
        'wildcard_min' => 0,
        'total_min' => 1,
    ]);
    createApiServiceProductPrice($product->id, $user);

    $this->mockSourceApi->shouldReceive('isConfigured')->andReturn(true);
    $this->mockSourceApi->shouldReceive('prepareOrder')
        ->once()
        ->withArgs(function ($customer, $productCode, $referId) {
            return $customer === 'acme_test@example.com'
                && $productCode === '12345';
        })
        ->andReturn([
            'code' => 1,
            'data' => ['id' => 777],
        ]);

    $result = $this->service->prepareOrder('acme_test@example.com', '12345');

    expect($result['code'])->toBe(1);
    expect($result['data'])->toHaveKeys(['id', 'cert_id']);

    // 验证 Order 创建
    $order = AcmeOrder::where('user_id', $user->id)->latest()->first();
    expect($order)->not->toBeNull();

    // 验证 Cert 创建 + api_id 映射
    $cert = AcmeCert::where('order_id', $order->id)->latest()->first();
    expect($cert)->not->toBeNull();
    expect($cert->channel)->toBe('api');
    expect((int) $cert->api_id)->toBe(777);
});

test('prepare order fails when user not found', function () {
    $result = $this->service->prepareOrder('nonexistent@example.com', '11111');

    expect($result['code'])->toBe(0);
    expect($result['msg'])->toContain('User not found');
});

test('prepare order fails when product not found', function () {
    $this->createTestUser(['email' => 'product_test@example.com']);

    $result = $this->service->prepareOrder('product_test@example.com', '99999');

    expect($result['code'])->toBe(0);
    expect($result['msg'])->toContain('Product not found');
});

test('prepare order reuses existing order', function () {
    $user = $this->createTestUser(['balance' => '500.00', 'email' => 'reuse@example.com']);
    $product = $this->createTestProduct([
        'product_type' => 'acme',
        'api_id' => 33333,
        'standard_min' => 1,
        'wildcard_min' => 0,
        'total_min' => 1,
    ]);
    createApiServiceProductPrice($product->id, $user);

    // 创建已有 Order
    $existingOrder = $this->createTestAcmeOrder($user, $product, [
        'period_till' => now()->addYear(),
        'purchased_standard_count' => 1,
        'purchased_wildcard_count' => 0,
    ]);
    $existingCert = $this->createTestAcmeCert($existingOrder, ['channel' => 'api']);

    $this->mockSourceApi->shouldReceive('isConfigured')->andReturn(true);
    $this->mockSourceApi->shouldReceive('prepareOrder')
        ->once()
        ->andReturn(['code' => 1, 'data' => ['id' => 777]]);

    $result = $this->service->prepareOrder('reuse@example.com', '33333');
    expect($result['code'])->toBe(1);

    // 应该复用已有 Order，不创建新的
    $orderCount = AcmeOrder::where('user_id', $user->id)->where('product_id', $product->id)->count();
    expect($orderCount)->toBe(1);
});

test('prepare order recovery returns existing when refer id has api id', function () {
    $user = $this->createTestUser(['balance' => '500.00', 'email' => 'recover@example.com']);
    $product = $this->createTestProduct([
        'product_type' => 'acme',
        'api_id' => 44444,
    ]);
    $order = $this->createTestAcmeOrder($user, $product);
    $cert = $this->createTestAcmeCert($order, [
        'channel' => 'api',
        'refer_id' => 'existing-refer-id',
    ]);
    $cert->update(['api_id' => 999]);

    // 不应调用上游
    $this->mockSourceApi->shouldNotReceive('prepareOrder');

    $result = $this->service->prepareOrder('recover@example.com', '44444', 'existing-refer-id');

    expect($result['code'])->toBe(1);
    expect($result['data']['id'])->toBe($order->id);
    expect($result['data']['cert_id'])->toBe($cert->id);
});

// ==================== submitDomains 测试 ====================

test('submit domains creates authorizations', function () {
    $user = $this->createTestUser(['balance' => '500.00', 'email' => 'domains@example.com']);
    $product = $this->createTestProduct([
        'product_type' => 'acme',
        'api_id' => 55555,
        'standard_min' => 1,
        'wildcard_min' => 0,
        'total_min' => 1,
    ]);
    $order = $this->createTestAcmeOrder($user, $product, [
        'purchased_standard_count' => 10,
        'purchased_wildcard_count' => 10,
    ]);
    $cert = $this->createTestAcmeCert($order, [
        'channel' => 'api',
        'status' => 'processing',
    ]);
    $cert->update(['api_id' => 777, 'cert' => null]);

    $this->mockSourceApi->shouldReceive('isConfigured')->andReturn(true);
    $this->mockSourceApi->shouldReceive('submitDomains')
        ->once()
        ->withArgs(function ($orderId, $domains) {
            return $orderId === 777
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

    $result = $this->service->submitDomains($order->id, ['example.com']);

    expect($result['code'])->toBe(1);
    expect($result['data']['id'])->toBe($order->id);
    expect($result['data'])->toHaveKey('authorizations');

    // 验证 authorization 创建 + challenge ID 映射
    $updatedCert = AcmeCert::find($order->fresh()->latest_cert_id);
    $authorization = $updatedCert->acmeAuthorizations->first();
    expect($authorization)->not->toBeNull();
    expect((int) $authorization->acme_challenge_id)->toBe(888);
});

test('submit domains idempotent when authorizations exist', function () {
    $user = $this->createTestUser(['email' => 'idempotent@example.com']);
    $product = $this->createTestProduct(['product_type' => 'acme', 'api_id' => 55555]);
    $order = $this->createTestAcmeOrder($user, $product);
    $cert = $this->createTestAcmeCert($order, [
        'channel' => 'api',
        'status' => 'processing',
    ]);
    $cert->update(['api_id' => 777]);

    Authorization::create([
        'cert_id' => $cert->id,
        'token' => 'existing-token',
        'identifier_type' => 'dns',
        'identifier_value' => 'example.com',
        'wildcard' => false,
        'status' => 'pending',
        'expires_at' => now()->addDays(7),
        'challenge_type' => 'dns-01',
        'challenge_token' => 'chall-token',
        'acme_challenge_id' => 888,
        'key_authorization' => 'key-auth',
        'challenge_status' => 'pending',
    ]);

    // 不应调用上游
    $this->mockSourceApi->shouldNotReceive('submitDomains');

    $result = $this->service->submitDomains($order->id, ['example.com']);

    expect($result['code'])->toBe(1);
    expect($result['data']['id'])->toBe($order->id);
});

test('submit domains fails when order not found', function () {
    $result = $this->service->submitDomains(99999, ['example.com']);

    expect($result['code'])->toBe(0);
    expect($result['msg'])->toContain('Order not found');
});

// ==================== 两步集成测试 ====================

test('prepare and submit domains two step flow maps ids', function () {
    $user = $this->createTestUser(['balance' => '500.00', 'email' => 'twostep@example.com']);
    $product = $this->createTestProduct([
        'product_type' => 'acme',
        'api_id' => 55555,
        'standard_min' => 1,
        'wildcard_min' => 0,
        'total_min' => 1,
    ]);
    createApiServiceProductPrice($product->id, $user);

    // 步骤1：prepareOrder
    $this->mockSourceApi->shouldReceive('isConfigured')->andReturn(true);
    $this->mockSourceApi->shouldReceive('prepareOrder')
        ->once()
        ->withArgs(function ($customer, $productCode, $referId) {
            return $customer === 'twostep@example.com'
                && $productCode === '55555';
        })
        ->andReturn(['code' => 1, 'data' => ['id' => 777]]);

    $prepareResult = $this->service->prepareOrder('twostep@example.com', '55555');
    expect($prepareResult['code'])->toBe(1);

    $orderId = $prepareResult['data']['id'];

    // 步骤2：submitDomains
    $this->mockSourceApi->shouldReceive('submitDomains')
        ->once()
        ->withArgs(function ($upstreamId, $domains) {
            return $upstreamId === 777
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

    $submitResult = $this->service->submitDomains($orderId, ['example.com']);

    expect($submitResult['code'])->toBe(1);
    // 返回本级 order.id，不是上游 777
    expect($submitResult['data']['id'])->toBe($orderId);
    expect($submitResult['data']['id'])->not->toBe(777);

    // cert.api_id 存储了上游 ID
    $updatedOrder = AcmeOrder::find($orderId);
    $updatedCert = AcmeCert::find($updatedOrder->latest_cert_id);
    expect((int) $updatedCert->api_id)->toBe(777);

    // AcmeAuthorization.acme_challenge_id 存储了上游 challenge ID
    $authorization = $updatedCert->acmeAuthorizations->first();
    expect($authorization)->not->toBeNull();
    expect((int) $authorization->acme_challenge_id)->toBe(888);
});

// ==================== reissue / challenge / finalize / revoke ====================

test('reissue order calls upstream reissue', function () {
    $user = $this->createTestUser(['balance' => '500.00', 'email' => 'reissue@example.com']);
    $product = $this->createTestProduct([
        'product_type' => 'acme',
        'api_id' => 66666,
        'standard_min' => 1,
        'wildcard_min' => 0,
        'total_min' => 1,
        'reissue' => 1,
    ]);
    createApiServiceProductPrice($product->id, $user);

    $order = $this->createTestAcmeOrder($user, $product, [
        'period_till' => now()->addYear(),
        'purchased_standard_count' => 3,
        'purchased_wildcard_count' => 0,
    ]);
    // 已签发的 cert（有 api_id）
    $cert = $this->createTestAcmeCert($order, [
        'channel' => 'api',
        'status' => 'active',
    ]);
    $cert->update(['api_id' => 999]);

    $this->mockSourceApi->shouldReceive('isConfigured')->andReturn(true);
    $this->mockSourceApi->shouldReceive('reissueOrder')
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
        'product_type' => 'acme',
        'api_id' => 77777,
        'reissue' => 1,
    ]);
    createApiServiceProductPrice($product->id, $user);

    $order = $this->createTestAcmeOrder($user, $product, [
        'period_till' => now()->addYear(),
        'purchased_standard_count' => 1,
    ]);
    // cert 没有 api_id
    $cert = $this->createTestAcmeCert($order, [
        'channel' => 'api',
        'status' => 'pending',
    ]);
    $cert->update(['api_id' => null]);

    $this->mockSourceApi->shouldReceive('isConfigured')->andReturn(true);

    $result = $this->service->reissueOrder($order->id, ['example.com']);

    expect($result['code'])->toBe(0);
    expect($result['msg'])->toContain('No upstream order ID');
});

test('reissue order does not recover cert from another order with same user refer id', function () {
    $user = $this->createTestUser(['balance' => '500.00', 'email' => 'reissue-scope@example.com']);
    $product = $this->createTestProduct([
        'product_type' => 'acme',
        'api_id' => 88888,
        'standard_min' => 1,
        'wildcard_min' => 0,
        'total_min' => 1,
        'reissue' => 1,
    ]);
    createApiServiceProductPrice($product->id, $user);

    $targetOrder = $this->createTestAcmeOrder($user, $product, [
        'period_till' => now()->addYear(),
        'purchased_standard_count' => 1,
    ]);
    $targetCert = $this->createTestAcmeCert($targetOrder, [
        'channel' => 'api',
        'status' => 'active',
    ]);
    $targetCert->update(['api_id' => 3001]);

    $otherOrder = $this->createTestAcmeOrder($user, $product, [
        'period_till' => now()->addYear(),
        'purchased_standard_count' => 1,
    ]);
    $otherCert = $this->createTestAcmeCert($otherOrder, [
        'channel' => 'api',
        'status' => 'processing',
        // 这个 refer_id 属于同一用户的另一张订单，不能被当前 orderId 的恢复逻辑误命中。
        'refer_id' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
    ]);
    $otherCert->update(['api_id' => 4001]);

    $this->mockSourceApi->shouldReceive('isConfigured')->andReturn(true);
    $this->mockSourceApi->shouldReceive('reissueOrder')
        ->once()
        ->withArgs(function ($orderId, $domains, $referId) {
            return $orderId === 3001
                && $domains === ['current.example.com']
                && $referId !== 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        })
        ->andReturn([
            'code' => 1,
            'data' => [
                'id' => 5001,
                'authorizations' => [
                    [
                        'identifier' => ['type' => 'dns', 'value' => 'current.example.com'],
                        'status' => 'pending',
                        'challenges' => [
                            ['id' => 6001, 'type' => 'dns-01', 'token' => 'target-token', 'key_authorization' => 'target-ka', 'status' => 'pending'],
                        ],
                    ],
                ],
            ],
        ]);

    $result = $this->service->reissueOrder(
        $targetOrder->id,
        ['current.example.com'],
        'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
    );

    expect($result['code'])->toBe(1);
    expect($result['data']['id'])->toBe($targetOrder->id);
    expect($result['data']['id'])->not->toBe($otherOrder->id);
});

test('respond to challenge with upstream maps challenge id', function () {
    $user = $this->createTestUser(['email' => 'challenge_map@example.com']);
    $product = $this->createTestProduct(['product_type' => 'acme', 'api_id' => 12345]);
    $order = $this->createTestAcmeOrder($user, $product);
    $cert = $this->createTestAcmeCert($order, ['channel' => 'api']);

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
    $this->mockSourceApi->shouldReceive('isConfigured')->andReturn(true);
    $this->mockSourceApi->shouldReceive('respondToChallenge')
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
    $product = $this->createTestProduct(['product_type' => 'acme', 'api_id' => 12345]);
    $order = $this->createTestAcmeOrder($user, $product);
    $cert = $this->createTestAcmeCert($order, [
        'channel' => 'api',
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
    $this->mockSourceApi->shouldReceive('isConfigured')->andReturn(true);
    $this->mockSourceApi->shouldReceive('finalizeOrder')
        ->once()
        ->with(999, Mockery::type('string'))
        ->andReturn(['code' => 1, 'data' => []]);
    $this->mockSourceApi->shouldReceive('getCertificate')
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

test('revoke certificate passes reason to upstream', function () {
    $user = $this->createTestUser(['email' => 'revoke_reason@example.com']);
    $product = $this->createTestProduct(['product_type' => 'acme', 'api_id' => 12345]);
    $order = $this->createTestAcmeOrder($user, $product);
    $cert = $this->createTestAcmeCert($order, [
        'channel' => 'api',
        'status' => 'active',
        'serial_number' => 'ABCD1234',
    ]);

    $this->mockSourceApi->shouldReceive('isConfigured')->andReturn(true);
    $this->mockSourceApi->shouldReceive('revokeCertificate')
        ->once()
        ->with('ABCD1234', 'KEY_COMPROMISE')
        ->andReturn(['code' => 1]);

    $result = $this->service->revokeCertificate('ABCD1234', 'KEY_COMPROMISE');

    expect($result['code'])->toBe(1);
    expect($cert->fresh()->status)->toBe('revoked');
});

test('format order always returns local id', function () {
    $user = $this->createTestUser(['email' => 'format_id@example.com']);
    $product = $this->createTestProduct(['product_type' => 'acme', 'api_id' => 12345]);
    $order = $this->createTestAcmeOrder($user, $product);
    $cert = $this->createTestAcmeCert($order, [
        'channel' => 'api',
        'status' => 'pending',
    ]);
    $cert->update(['api_id' => 777]);

    // getOrder 内部调用 formatOrder，应返回 order.id 而非 api_id
    $result = $this->service->getOrder($order->id);

    expect($result['code'])->toBe(1);
    expect($result['data']['id'])->toBe($order->id);
    expect($result['data']['id'])->not->toBe(777);
});
