<?php

use App\Models\Acme\Account;
use App\Models\Cert;
use App\Models\User;
use App\Services\Acme\ApiClient;
use App\Services\Acme\JwsService;
use App\Services\Acme\NonceService;
use App\Services\Acme\OrderService;

uses(Tests\Traits\MocksAcmeJws::class);

beforeEach(function () {
    $mockNonce = Mockery::mock(NonceService::class);
    $mockNonce->shouldReceive('generate')->andReturn('test-nonce');
    app()->instance(NonceService::class, $mockNonce);

    $this->mockAcmeJwsMiddleware();
});

test('下载证书-订单不存在', function () {
    $mockOrderService = Mockery::mock(OrderService::class);
    $mockOrderService->shouldReceive('get')
        ->with('nonexistent')
        ->andReturn(null);
    app()->instance(OrderService::class, $mockOrderService);

    $this->postJson('/acme/cert/nonexistent', [])
        ->assertStatus(404)
        ->assertJson([
            'type' => 'about:blank',
            'detail' => 'Certificate not found',
        ]);
});

test('下载证书-证书未就绪', function () {
    $user = User::factory()->create();
    $account = Account::create([
        'user_id' => $user->id,
        'key_id' => 'test-key',
        'public_key' => ['kty' => 'RSA'],
        'status' => 'valid',
    ]);

    $cert = Cert::factory()->create([
        'refer_id' => 'not-ready',
        'cert' => null,
        'csr' => null,
    ]);

    $this->withAcmeAccount($account);
    $this->withAcmeJws(['payload' => [], 'protected' => []]);

    $mockOrderService = Mockery::mock(OrderService::class);
    $mockOrderService->shouldReceive('get')
        ->with('not-ready')
        ->andReturn($cert);
    $mockOrderService->shouldReceive('verifyOwnership')
        ->andReturn(true);
    app()->instance(OrderService::class, $mockOrderService);

    $this->postJson('/acme/cert/not-ready', [])
        ->assertStatus(403)
        ->assertJson([
            'type' => 'urn:ietf:params:acme:error:orderNotReady',
        ]);
});

test('下载证书-成功', function () {
    $user = User::factory()->create();
    $account = Account::create([
        'user_id' => $user->id,
        'key_id' => 'test-key',
        'public_key' => ['kty' => 'RSA'],
        'status' => 'valid',
    ]);

    $certPem = "-----BEGIN CERTIFICATE-----\nMIIBtest\n-----END CERTIFICATE-----";
    $intermediatePem = "-----BEGIN CERTIFICATE-----\nMIIBintermediate\n-----END CERTIFICATE-----";

    // 先创建 Chain 记录用于中间证书查询
    \App\Models\Chain::create([
        'common_name' => 'Test CA',
        'intermediate_cert' => $intermediatePem,
    ]);

    $cert = Cert::factory()->active()->create([
        'refer_id' => 'ready-cert',
        'cert' => $certPem,
        'issuer' => 'Test CA',
    ]);

    $this->withAcmeAccount($account);
    $this->withAcmeJws(['payload' => [], 'protected' => []]);

    $mockOrderService = Mockery::mock(OrderService::class);
    $mockOrderService->shouldReceive('get')
        ->with('ready-cert')
        ->andReturn($cert);
    $mockOrderService->shouldReceive('verifyOwnership')
        ->andReturn(true);
    app()->instance(OrderService::class, $mockOrderService);

    $response = $this->postJson('/acme/cert/ready-cert', []);
    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pem-certificate-chain');
    $response->assertHeader('Replay-Nonce', 'test-nonce');

    $content = $response->getContent();
    expect($content)->toContain('BEGIN CERTIFICATE');
    expect($content)->toContain('MIIBintermediate');
});

test('下载证书-不属于当前账户', function () {
    $user = User::factory()->create();
    $account = Account::create([
        'user_id' => $user->id,
        'key_id' => 'test-key',
        'public_key' => ['kty' => 'RSA'],
        'status' => 'valid',
    ]);

    $cert = Cert::factory()->active()->create([
        'refer_id' => 'other-cert',
        'cert' => 'test',
    ]);

    $this->withAcmeAccount($account);
    $this->withAcmeJws(['payload' => [], 'protected' => []]);

    $mockOrderService = Mockery::mock(OrderService::class);
    $mockOrderService->shouldReceive('get')
        ->with('other-cert')
        ->andReturn($cert);
    $mockOrderService->shouldReceive('verifyOwnership')
        ->andReturn(false);
    app()->instance(OrderService::class, $mockOrderService);

    $this->postJson('/acme/cert/other-cert', [])
        ->assertStatus(403)
        ->assertJson([
            'type' => 'urn:ietf:params:acme:error:unauthorized',
        ]);
});

test('吊销证书-缺少 certificate 参数', function () {
    $this->withAcmeJws([
        'payload' => ['certificate' => ''],
        'protected' => [],
    ]);

    $this->postJson('/acme/revoke-cert', [])
        ->assertStatus(400)
        ->assertJson([
            'type' => 'urn:ietf:params:acme:error:malformed',
            'detail' => 'Certificate is required',
        ]);
});
