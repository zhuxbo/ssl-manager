<?php

use App\Models\Acme\Account;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Acme\AccountService;
use App\Services\Acme\JwsService;
use App\Services\Acme\NonceService;

uses(Tests\Traits\MocksAcmeJws::class);

/**
 * 模拟 ACME JWS 中间件，直接注入 acme_jws 和 acme_account 属性
 */
beforeEach(function () {
    // 模拟 NonceService
    $mockNonce = Mockery::mock(NonceService::class);
    $mockNonce->shouldReceive('generate')->andReturn('test-nonce');
    app()->instance(NonceService::class, $mockNonce);

    $this->mockAcmeJwsMiddleware();
});

test('注册新账户-缺少 EAB', function () {
    // 模拟 JWS 数据
    $this->withAcmeJws([
        'payload' => [
            'termsOfServiceAgreed' => true,
            'contact' => ['mailto:test@example.com'],
        ],
        'protected' => [
            'jwk' => ['kty' => 'RSA', 'n' => 'test', 'e' => 'AQAB'],
        ],
    ]);

    $mockJws = Mockery::mock(JwsService::class);
    $mockJws->shouldReceive('extractPublicKey')->andReturn(['kty' => 'RSA', 'n' => 'test', 'e' => 'AQAB']);
    $mockJws->shouldReceive('computeKeyId')->andReturn('test-key-id');
    app()->instance(JwsService::class, $mockJws);

    $this->postJson('/acme/new-acct', [])
        ->assertStatus(400)
        ->assertJson([
            'type' => 'urn:ietf:params:acme:error:externalAccountRequired',
        ]);
});

test('查找已存在的账户-onlyReturnExisting', function () {
    $user = User::factory()->create();
    $account = Account::create([
        'user_id' => $user->id,
        'key_id' => 'existing-key-id',
        'public_key' => ['kty' => 'RSA', 'n' => 'test', 'e' => 'AQAB'],
        'status' => 'valid',
    ]);

    $this->withAcmeJws([
        'payload' => [
            'onlyReturnExisting' => true,
        ],
        'protected' => [
            'jwk' => ['kty' => 'RSA', 'n' => 'test', 'e' => 'AQAB'],
        ],
    ]);

    $mockJws = Mockery::mock(JwsService::class);
    $mockJws->shouldReceive('extractPublicKey')->andReturn(['kty' => 'RSA', 'n' => 'test', 'e' => 'AQAB']);
    $mockJws->shouldReceive('computeKeyId')->andReturn('existing-key-id');
    app()->instance(JwsService::class, $mockJws);

    $mockAccountService = Mockery::mock(AccountService::class);
    $mockAccountService->shouldReceive('findByKeyId')
        ->with('existing-key-id')
        ->andReturn($account);
    $mockAccountService->shouldReceive('getAccountUrl')
        ->andReturn('https://example.com/acme/acct/existing-key-id');
    $mockAccountService->shouldReceive('formatResponse')
        ->andReturn(['status' => 'valid', 'contact' => []]);
    app()->instance(AccountService::class, $mockAccountService);

    $this->postJson('/acme/new-acct', [])
        ->assertOk()
        ->assertHeader('Location');
});

test('查找不存在的账户-onlyReturnExisting 返回404', function () {
    $this->withAcmeJws([
        'payload' => [
            'onlyReturnExisting' => true,
        ],
        'protected' => [
            'jwk' => ['kty' => 'RSA', 'n' => 'test', 'e' => 'AQAB'],
        ],
    ]);

    $mockJws = Mockery::mock(JwsService::class);
    $mockJws->shouldReceive('extractPublicKey')->andReturn(['kty' => 'RSA', 'n' => 'test', 'e' => 'AQAB']);
    $mockJws->shouldReceive('computeKeyId')->andReturn('nonexistent-key-id');
    app()->instance(JwsService::class, $mockJws);

    $mockAccountService = Mockery::mock(AccountService::class);
    $mockAccountService->shouldReceive('findByKeyId')
        ->with('nonexistent-key-id')
        ->andReturn(null);
    app()->instance(AccountService::class, $mockAccountService);

    $this->postJson('/acme/new-acct', [])
        ->assertStatus(404)
        ->assertJson([
            'type' => 'urn:ietf:params:acme:error:accountDoesNotExist',
        ]);
});

test('注册新账户-无效 EAB kid', function () {
    $mockJws = Mockery::mock(JwsService::class);
    $mockJws->shouldReceive('extractPublicKey')->andReturn(['kty' => 'RSA', 'n' => 'test', 'e' => 'AQAB']);
    $mockJws->shouldReceive('base64UrlDecode')
        ->with(Mockery::any())
        ->andReturn('{"alg":"HS256","kid":"","url":"https://example.com/acme/new-acct"}');
    app()->instance(JwsService::class, $mockJws);

    $this->withAcmeJws([
        'payload' => [
            'termsOfServiceAgreed' => true,
            'contact' => ['mailto:test@example.com'],
            'externalAccountBinding' => [
                'protected' => base64_encode('{"alg":"HS256","kid":""}'),
                'payload' => base64_encode('{}'),
                'signature' => 'test',
            ],
        ],
        'protected' => [
            'jwk' => ['kty' => 'RSA', 'n' => 'test', 'e' => 'AQAB'],
        ],
    ]);

    $this->postJson('/acme/new-acct', [])
        ->assertStatus(400);
});

test('注册新账户-EAB 对应订单不存在返回 401', function () {
    $mockJws = Mockery::mock(JwsService::class);
    $mockJws->shouldReceive('extractPublicKey')->andReturn(['kty' => 'RSA', 'n' => 'test', 'e' => 'AQAB']);
    $mockJws->shouldReceive('base64UrlDecode')
        ->andReturnUsing(function ($input) {
            return base64_decode(strtr($input, '-_', '+/'));
        });
    app()->instance(JwsService::class, $mockJws);

    $eabProtected = base64_encode(json_encode(['alg' => 'HS256', 'kid' => 'nonexistent-kid', 'url' => url('/acme/new-acct')]));
    $eabProtected = rtrim(strtr($eabProtected, '+/', '-_'), '=');

    $this->withAcmeJws([
        'payload' => [
            'termsOfServiceAgreed' => true,
            'contact' => ['mailto:test@example.com'],
            'externalAccountBinding' => [
                'protected' => $eabProtected,
                'payload' => 'e30',
                'signature' => 'test-sig',
            ],
        ],
        'protected' => [
            'jwk' => ['kty' => 'RSA', 'n' => 'test', 'e' => 'AQAB'],
        ],
    ]);

    $this->postJson('/acme/new-acct', [])
        ->assertStatus(401)
        ->assertJson([
            'type' => 'urn:ietf:params:acme:error:unauthorized',
        ]);
});
