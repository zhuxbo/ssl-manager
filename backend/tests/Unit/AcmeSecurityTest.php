<?php

use App\Services\Acme\JwsService;
use App\Services\Acme\NonceService;

uses(Tests\TestCase::class);

beforeEach(function () {
    // 使用数组缓存避免 Redis 依赖
    config(['cache.default' => 'array']);

    $this->jwsService = new JwsService;
    $this->nonceService = new NonceService;
});

/**
 * 测试 Nonce 防重放：同一 Nonce 只能使用一次
 */
test('nonce cannot be reused', function () {
    $nonce = $this->nonceService->generate();

    // 第一次验证应该成功
    expect($this->nonceService->verify($nonce))->toBeTrue();

    // 第二次验证应该失败（重放攻击）
    expect($this->nonceService->verify($nonce))->toBeFalse();
});

/**
 * 测试 Nonce 生成唯一性
 */
test('nonce is unique', function () {
    $nonces = [];
    for ($i = 0; $i < 100; $i++) {
        $nonces[] = $this->nonceService->generate();
    }

    $uniqueNonces = array_unique($nonces);
    expect($uniqueNonces)->toHaveCount(100);
});

/**
 * 测试无效 Nonce 验证失败
 */
test('invalid nonce fails', function () {
    expect($this->nonceService->verify('invalid_nonce_123'))->toBeFalse();
    expect($this->nonceService->verify(''))->toBeFalse();
});

/**
 * 测试算法混淆防护：RSA 密钥不能使用 EC 算法
 */
test('rsa key rejects ec algorithm', function () {
    $rsaJwk = [
        'kty' => 'RSA',
        'n' => 'test_modulus',
        'e' => 'AQAB',
    ];

    $jws = [
        'protected' => ['alg' => 'ES256'],  // 尝试使用 EC 算法
        'payload' => [],
        'signature' => 'test',
        'raw_protected' => 'eyJhbGciOiJFUzI1NiJ9',
        'raw_payload' => '',
    ];

    // RSA 密钥使用 ES256 算法应该验证失败
    expect($this->jwsService->verify($jws, $rsaJwk))->toBeFalse();
});

/**
 * 测试算法混淆防护：EC 密钥不能使用 RSA 算法
 */
test('ec key rejects rsa algorithm', function () {
    $ecJwk = [
        'kty' => 'EC',
        'crv' => 'P-256',
        'x' => 'test_x',
        'y' => 'test_y',
    ];

    $jws = [
        'protected' => ['alg' => 'RS256'],  // 尝试使用 RSA 算法
        'payload' => [],
        'signature' => 'test',
        'raw_protected' => 'eyJhbGciOiJSUzI1NiJ9',
        'raw_payload' => '',
    ];

    // EC 密钥使用 RS256 算法应该验证失败
    expect($this->jwsService->verify($jws, $ecJwk))->toBeFalse();
});

/**
 * 测试算法混淆防护：EC 曲线必须匹配
 */
test('ec curve must match algorithm', function () {
    $ecJwk = [
        'kty' => 'EC',
        'crv' => 'P-256',  // P-256 曲线
        'x' => 'test_x',
        'y' => 'test_y',
    ];

    $jws = [
        'protected' => ['alg' => 'ES384'],  // ES384 需要 P-384 曲线
        'payload' => [],
        'signature' => 'test',
        'raw_protected' => 'eyJhbGciOiJFUzM4NCJ9',
        'raw_payload' => '',
    ];

    // P-256 曲线使用 ES384 算法应该验证失败
    expect($this->jwsService->verify($jws, $ecJwk))->toBeFalse();
});

/**
 * 测试不支持的密钥类型被拒绝
 */
test('unsupported key type rejected', function () {
    $unknownJwk = [
        'kty' => 'oct',  // 对称密钥，ACME 不支持
        'k' => 'test_key',
    ];

    $jws = [
        'protected' => ['alg' => 'HS256'],
        'payload' => [],
        'signature' => 'test',
        'raw_protected' => 'eyJhbGciOiJIUzI1NiJ9',
        'raw_payload' => '',
    ];

    expect($this->jwsService->verify($jws, $unknownJwk))->toBeFalse();
});

/**
 * 测试 JWK 指纹计算一致性
 */
test('jwk thumbprint consistency', function () {
    $rsaJwk = [
        'kty' => 'RSA',
        'n' => 'test_modulus_value',
        'e' => 'AQAB',
        'extra_field' => 'should_be_ignored',
    ];

    $keyId1 = $this->jwsService->computeKeyId($rsaJwk);
    $keyId2 = $this->jwsService->computeKeyId($rsaJwk);

    // 相同 JWK 应该产生相同的 keyId
    expect($keyId1)->toBe($keyId2);

    // keyId 应该是 base64url 编码的 SHA256 哈希
    expect($keyId1)->toMatch('/^[A-Za-z0-9_-]+$/');
});

/**
 * 测试 Base64URL 编解码
 */
test('base64url encoding', function () {
    $testData = 'Hello, World! +/=';

    $encoded = $this->jwsService->base64UrlEncode($testData);
    $decoded = $this->jwsService->base64UrlDecode($encoded);

    // 编码不应包含 +, /, =
    expect($encoded)->not->toContain('+');
    expect($encoded)->not->toContain('/');
    expect($encoded)->not->toContain('=');

    // 解码应该还原原始数据
    expect($decoded)->toBe($testData);
});
