<?php

use App\Models\Acme\Account;
use App\Models\Cert;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Acme\NonceService;
use App\Services\Acme\OrderService;

uses(Tests\Traits\MocksAcmeJws::class);

beforeEach(function () {
    $mockNonce = Mockery::mock(NonceService::class);
    $mockNonce->shouldReceive('generate')->andReturn('test-nonce');
    app()->instance(NonceService::class, $mockNonce);

    $this->mockAcmeJwsMiddleware();
});

test('创建新订单-缺少 identifiers', function () {
    $user = User::factory()->create();
    $account = Account::create([
        'user_id' => $user->id,
        'key_id' => 'test-key',
        'public_key' => ['kty' => 'RSA'],
        'status' => 'valid',
    ]);

    $this->withAcmeAccount($account);
    $this->withAcmeJws([
        'payload' => [
            'identifiers' => [],
        ],
        'protected' => [],
    ]);

    $this->postJson('/acme/new-order', [])
        ->assertStatus(400)
        ->assertJson([
            'type' => 'urn:ietf:params:acme:error:malformed',
        ]);
});

test('创建新订单-不支持的标识符类型', function () {
    $user = User::factory()->create();
    $account = Account::create([
        'user_id' => $user->id,
        'key_id' => 'test-key',
        'public_key' => ['kty' => 'RSA'],
        'status' => 'valid',
    ]);

    $this->withAcmeAccount($account);
    $this->withAcmeJws([
        'payload' => [
            'identifiers' => [
                ['type' => 'ip', 'value' => '1.2.3.4'],
            ],
        ],
        'protected' => [],
    ]);

    $this->postJson('/acme/new-order', [])
        ->assertStatus(400)
        ->assertJson([
            'type' => 'urn:ietf:params:acme:error:unsupportedIdentifier',
        ]);
});

test('创建新订单-成功', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['support_acme' => 1]);
    $account = Account::create([
        'user_id' => $user->id,
        'key_id' => 'test-key',
        'public_key' => ['kty' => 'RSA'],
        'status' => 'valid',
    ]);

    $order = Order::factory()->acme()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
    ]);
    $cert = Cert::factory()->acme()->create([
        'order_id' => $order->id,
        'refer_id' => 'test-refer-id',
    ]);
    $order->update(['latest_cert_id' => $cert->id]);

    $this->withAcmeAccount($account);
    $this->withAcmeJws([
        'payload' => [
            'identifiers' => [
                ['type' => 'dns', 'value' => 'example.com'],
            ],
        ],
        'protected' => [],
    ]);

    $mockOrderService = Mockery::mock(OrderService::class);
    $mockOrderService->shouldReceive('create')
        ->once()
        ->andReturn(['order' => $cert]);
    $mockOrderService->shouldReceive('getOrderUrl')
        ->andReturn('https://example.com/acme/order/test-refer-id');
    $mockOrderService->shouldReceive('formatOrderResponse')
        ->andReturn([
            'status' => 'pending',
            'identifiers' => [['type' => 'dns', 'value' => 'example.com']],
            'authorizations' => [],
            'finalize' => 'https://example.com/acme/order/test-refer-id/finalize',
        ]);
    app()->instance(OrderService::class, $mockOrderService);

    $this->postJson('/acme/new-order', [])
        ->assertStatus(201)
        ->assertHeader('Location')
        ->assertHeader('Replay-Nonce', 'test-nonce')
        ->assertJsonStructure(['status', 'identifiers', 'finalize']);
});

test('获取订单详情-订单不存在', function () {
    $mockOrderService = Mockery::mock(OrderService::class);
    $mockOrderService->shouldReceive('get')
        ->with('nonexistent')
        ->andReturn(null);
    app()->instance(OrderService::class, $mockOrderService);

    $this->postJson('/acme/order/nonexistent', [])
        ->assertStatus(404);
});

test('获取订单详情-成功', function () {
    $user = User::factory()->create();
    $account = Account::create([
        'user_id' => $user->id,
        'key_id' => 'test-key',
        'public_key' => ['kty' => 'RSA'],
        'status' => 'valid',
    ]);

    $order = Order::factory()->create(['user_id' => $user->id]);
    $cert = Cert::factory()->active()->create([
        'order_id' => $order->id,
        'refer_id' => 'test-refer',
    ]);

    $this->withAcmeAccount($account);
    $this->withAcmeJws(['payload' => [], 'protected' => []]);

    $mockOrderService = Mockery::mock(OrderService::class);
    $mockOrderService->shouldReceive('get')
        ->with('test-refer')
        ->andReturn($cert);
    $mockOrderService->shouldReceive('verifyOwnership')
        ->andReturn(true);
    $mockOrderService->shouldReceive('getAcmeStatus')
        ->andReturn('valid');
    $mockOrderService->shouldReceive('formatOrderResponse')
        ->andReturn(['status' => 'valid']);
    app()->instance(OrderService::class, $mockOrderService);

    $this->postJson('/acme/order/test-refer', [])
        ->assertOk()
        ->assertHeader('Replay-Nonce');
});

test('finalize 订单-缺少 CSR', function () {
    $user = User::factory()->create();
    $account = Account::create([
        'user_id' => $user->id,
        'key_id' => 'test-key',
        'public_key' => ['kty' => 'RSA'],
        'status' => 'valid',
    ]);

    $cert = Cert::factory()->create(['refer_id' => 'fin-refer']);

    $this->withAcmeAccount($account);
    $this->withAcmeJws([
        'payload' => ['csr' => ''],
        'protected' => [],
    ]);

    $mockOrderService = Mockery::mock(OrderService::class);
    $mockOrderService->shouldReceive('get')
        ->with('fin-refer')
        ->andReturn($cert);
    $mockOrderService->shouldReceive('verifyOwnership')
        ->andReturn(true);
    app()->instance(OrderService::class, $mockOrderService);

    $this->postJson('/acme/order/fin-refer/finalize', [])
        ->assertStatus(400)
        ->assertJson([
            'type' => 'urn:ietf:params:acme:error:badCSR',
        ]);
});

test('finalize 订单-成功', function () {
    $user = User::factory()->create();
    $account = Account::create([
        'user_id' => $user->id,
        'key_id' => 'test-key',
        'public_key' => ['kty' => 'RSA'],
        'status' => 'valid',
    ]);

    $cert = Cert::factory()->create(['refer_id' => 'fin-ok-refer']);

    $this->withAcmeAccount($account);
    $this->withAcmeJws([
        'payload' => ['csr' => 'valid-csr-base64url'],
        'protected' => [],
    ]);

    $mockOrderService = Mockery::mock(OrderService::class);
    $mockOrderService->shouldReceive('get')
        ->with('fin-ok-refer')
        ->andReturn($cert);
    $mockOrderService->shouldReceive('verifyOwnership')
        ->andReturn(true);
    $mockOrderService->shouldReceive('finalize')
        ->andReturn(['order' => $cert]);
    $mockOrderService->shouldReceive('getOrderUrl')
        ->andReturn('https://example.com/acme/order/fin-ok-refer');
    $mockOrderService->shouldReceive('formatOrderResponse')
        ->andReturn(['status' => 'processing']);
    app()->instance(OrderService::class, $mockOrderService);

    $this->postJson('/acme/order/fin-ok-refer/finalize', [])
        ->assertOk()
        ->assertHeader('Replay-Nonce')
        ->assertHeader('Location');
});

test('finalize 订单-不属于当前账户', function () {
    $user = User::factory()->create();
    $account = Account::create([
        'user_id' => $user->id,
        'key_id' => 'test-key',
        'public_key' => ['kty' => 'RSA'],
        'status' => 'valid',
    ]);

    $cert = Cert::factory()->create(['refer_id' => 'not-mine']);

    $this->withAcmeAccount($account);
    $this->withAcmeJws([
        'payload' => ['csr' => 'valid-csr'],
        'protected' => [],
    ]);

    $mockOrderService = Mockery::mock(OrderService::class);
    $mockOrderService->shouldReceive('get')
        ->with('not-mine')
        ->andReturn($cert);
    $mockOrderService->shouldReceive('verifyOwnership')
        ->andReturn(false);
    app()->instance(OrderService::class, $mockOrderService);

    $this->postJson('/acme/order/not-mine/finalize', [])
        ->assertStatus(403)
        ->assertJson([
            'type' => 'urn:ietf:params:acme:error:unauthorized',
        ]);
});
