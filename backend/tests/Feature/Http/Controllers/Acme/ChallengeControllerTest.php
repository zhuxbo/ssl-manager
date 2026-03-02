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

test('响应挑战-不存在', function () {
    $mockOrderService = Mockery::mock(OrderService::class);
    $mockOrderService->shouldReceive('getAuthorization')
        ->with('nonexistent-chall')
        ->andReturn(null);
    app()->instance(OrderService::class, $mockOrderService);

    $this->postJson('/acme/chall/nonexistent-chall', [])
        ->assertStatus(404)
        ->assertJson([
            'type' => 'about:blank',
            'detail' => 'Challenge not found',
        ]);
});

test('响应挑战-成功', function () {
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
        'token' => 'chall-token',
        'identifier_type' => 'dns',
        'identifier_value' => 'example.com',
        'status' => 'pending',
        'acme_challenge_id' => 1,
    ]);

    $this->withAcmeAccount($account);

    $mockOrderService = Mockery::mock(OrderService::class);
    $mockOrderService->shouldReceive('getAuthorization')
        ->with('chall-token')
        ->andReturn($authorization);
    $mockOrderService->shouldReceive('verifyOwnership')
        ->andReturn(true);
    $mockOrderService->shouldReceive('respondToChallenge')
        ->once()
        ->andReturn(['code' => 1]);
    $mockOrderService->shouldReceive('formatChallengeResponse')
        ->andReturn([
            'type' => 'dns-01',
            'status' => 'valid',
            'token' => 'chall-token',
        ]);
    $mockOrderService->shouldReceive('getAuthorizationUrl')
        ->andReturn('https://example.com/acme/authz/chall-token');
    app()->instance(OrderService::class, $mockOrderService);

    $this->postJson('/acme/chall/chall-token', [])
        ->assertOk()
        ->assertHeader('Replay-Nonce', 'test-nonce')
        ->assertHeader('Link')
        ->assertJsonStructure(['type', 'status', 'token']);
});

test('响应挑战-不属于当前账户', function () {
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
        'token' => 'other-chall',
        'identifier_type' => 'dns',
        'identifier_value' => 'example.com',
        'status' => 'pending',
    ]);

    $this->withAcmeAccount($account);

    $mockOrderService = Mockery::mock(OrderService::class);
    $mockOrderService->shouldReceive('getAuthorization')
        ->with('other-chall')
        ->andReturn($authorization);
    $mockOrderService->shouldReceive('verifyOwnership')
        ->andReturn(false);
    app()->instance(OrderService::class, $mockOrderService);

    $this->postJson('/acme/chall/other-chall', [])
        ->assertStatus(403)
        ->assertJson([
            'type' => 'urn:ietf:params:acme:error:unauthorized',
        ]);
});
