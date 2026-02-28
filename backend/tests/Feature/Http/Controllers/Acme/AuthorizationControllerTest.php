<?php

use App\Models\Acme\Account;
use App\Models\Acme\Authorization;
use App\Models\Cert;
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

test('获取授权-不存在', function () {
    $mockOrderService = Mockery::mock(OrderService::class);
    $mockOrderService->shouldReceive('getAuthorization')
        ->with('nonexistent-token')
        ->andReturn(null);
    app()->instance(OrderService::class, $mockOrderService);

    $this->postJson('/acme/authz/nonexistent-token', [])
        ->assertStatus(404)
        ->assertJson([
            'type' => 'about:blank',
            'detail' => 'Authorization not found',
        ]);
});

test('获取授权-成功', function () {
    $user = User::factory()->create();
    $account = Account::create([
        'user_id' => $user->id,
        'key_id' => 'test-key',
        'public_key' => ['kty' => 'RSA'],
        'status' => 'valid',
    ]);

    $cert = Cert::factory()->create();
    $authorization = Authorization::create([
        'cert_id' => $cert->id,
        'token' => 'valid-token',
        'identifier_type' => 'dns',
        'identifier_value' => 'example.com',
        'status' => 'valid',
        'acme_challenge_id' => null,
    ]);

    $this->withAcmeAccount($account);
    $this->withAcmeJws(['payload' => [], 'protected' => []]);

    $mockOrderService = Mockery::mock(OrderService::class);
    $mockOrderService->shouldReceive('getAuthorization')
        ->with('valid-token')
        ->andReturn($authorization);
    $mockOrderService->shouldReceive('verifyOwnership')
        ->andReturn(true);
    $mockOrderService->shouldReceive('formatAuthorizationResponse')
        ->andReturn([
            'status' => 'valid',
            'identifier' => ['type' => 'dns', 'value' => 'example.com'],
            'challenges' => [],
        ]);
    app()->instance(OrderService::class, $mockOrderService);

    $this->postJson('/acme/authz/valid-token', [])
        ->assertOk()
        ->assertHeader('Replay-Nonce', 'test-nonce')
        ->assertJsonStructure(['status', 'identifier', 'challenges']);
});

test('获取授权-不属于当前账户', function () {
    $user = User::factory()->create();
    $account = Account::create([
        'user_id' => $user->id,
        'key_id' => 'test-key',
        'public_key' => ['kty' => 'RSA'],
        'status' => 'valid',
    ]);

    $cert = Cert::factory()->create();
    $authorization = Authorization::create([
        'cert_id' => $cert->id,
        'token' => 'other-token',
        'identifier_type' => 'dns',
        'identifier_value' => 'example.com',
        'status' => 'pending',
        'acme_challenge_id' => null,
    ]);

    $this->withAcmeAccount($account);
    $this->withAcmeJws(['payload' => [], 'protected' => []]);

    $mockOrderService = Mockery::mock(OrderService::class);
    $mockOrderService->shouldReceive('getAuthorization')
        ->with('other-token')
        ->andReturn($authorization);
    $mockOrderService->shouldReceive('verifyOwnership')
        ->andReturn(false);
    app()->instance(OrderService::class, $mockOrderService);

    $this->postJson('/acme/authz/other-token', [])
        ->assertStatus(403)
        ->assertJson([
            'type' => 'urn:ietf:params:acme:error:unauthorized',
        ]);
});

test('GET 获取授权-成功', function () {
    $authorization = Authorization::create([
        'cert_id' => Cert::factory()->create()->id,
        'token' => 'get-token',
        'identifier_type' => 'dns',
        'identifier_value' => 'example.com',
        'status' => 'valid',
        'acme_challenge_id' => null,
    ]);

    $mockOrderService = Mockery::mock(OrderService::class);
    $mockOrderService->shouldReceive('getAuthorization')
        ->with('get-token')
        ->andReturn($authorization);
    $mockOrderService->shouldReceive('formatAuthorizationResponse')
        ->andReturn([
            'status' => 'valid',
            'identifier' => ['type' => 'dns', 'value' => 'example.com'],
            'challenges' => [],
        ]);
    app()->instance(OrderService::class, $mockOrderService);

    $this->getJson('/acme/authz/get-token')
        ->assertOk()
        ->assertHeader('Replay-Nonce');
});
