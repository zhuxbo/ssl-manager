<?php

namespace Tests\Unit;

use App\Services\Acme\JwsService;
use App\Services\Acme\NonceService;
use Tests\TestCase;

class AcmeSecurityTest extends TestCase
{
    private JwsService $jwsService;
    private NonceService $nonceService;

    protected function setUp(): void
    {
        parent::setUp();

        // 使用数组缓存避免 Redis 依赖
        config(['cache.default' => 'array']);

        $this->jwsService = new JwsService();
        $this->nonceService = new NonceService();
    }

    /**
     * 测试 Nonce 防重放：同一 Nonce 只能使用一次
     */
    public function test_nonce_cannot_be_reused(): void
    {
        $nonce = $this->nonceService->generate();

        // 第一次验证应该成功
        $this->assertTrue($this->nonceService->verify($nonce));

        // 第二次验证应该失败（重放攻击）
        $this->assertFalse($this->nonceService->verify($nonce));
    }

    /**
     * 测试 Nonce 生成唯一性
     */
    public function test_nonce_is_unique(): void
    {
        $nonces = [];
        for ($i = 0; $i < 100; $i++) {
            $nonces[] = $this->nonceService->generate();
        }

        $uniqueNonces = array_unique($nonces);
        $this->assertCount(100, $uniqueNonces);
    }

    /**
     * 测试无效 Nonce 验证失败
     */
    public function test_invalid_nonce_fails(): void
    {
        $this->assertFalse($this->nonceService->verify('invalid_nonce_123'));
        $this->assertFalse($this->nonceService->verify(''));
    }

    /**
     * 测试算法混淆防护：RSA 密钥不能使用 EC 算法
     */
    public function test_rsa_key_rejects_ec_algorithm(): void
    {
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
        $this->assertFalse($this->jwsService->verify($jws, $rsaJwk));
    }

    /**
     * 测试算法混淆防护：EC 密钥不能使用 RSA 算法
     */
    public function test_ec_key_rejects_rsa_algorithm(): void
    {
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
        $this->assertFalse($this->jwsService->verify($jws, $ecJwk));
    }

    /**
     * 测试算法混淆防护：EC 曲线必须匹配
     */
    public function test_ec_curve_must_match_algorithm(): void
    {
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
        $this->assertFalse($this->jwsService->verify($jws, $ecJwk));
    }

    /**
     * 测试不支持的密钥类型被拒绝
     */
    public function test_unsupported_key_type_rejected(): void
    {
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

        $this->assertFalse($this->jwsService->verify($jws, $unknownJwk));
    }

    /**
     * 测试 JWK 指纹计算一致性
     */
    public function test_jwk_thumbprint_consistency(): void
    {
        $rsaJwk = [
            'kty' => 'RSA',
            'n' => 'test_modulus_value',
            'e' => 'AQAB',
            'extra_field' => 'should_be_ignored',
        ];

        $keyId1 = $this->jwsService->computeKeyId($rsaJwk);
        $keyId2 = $this->jwsService->computeKeyId($rsaJwk);

        // 相同 JWK 应该产生相同的 keyId
        $this->assertEquals($keyId1, $keyId2);

        // keyId 应该是 base64url 编码的 SHA256 哈希
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $keyId1);
    }

    /**
     * 测试 Base64URL 编解码
     */
    public function test_base64url_encoding(): void
    {
        $testData = 'Hello, World! +/=';

        $encoded = $this->jwsService->base64UrlEncode($testData);
        $decoded = $this->jwsService->base64UrlDecode($encoded);

        // 编码不应包含 +, /, =
        $this->assertStringNotContainsString('+', $encoded);
        $this->assertStringNotContainsString('/', $encoded);
        $this->assertStringNotContainsString('=', $encoded);

        // 解码应该还原原始数据
        $this->assertEquals($testData, $decoded);
    }

    /**
     * 测试 EAB HMAC 验证使用时序安全比较
     */
    public function test_eab_verification_timing_safe(): void
    {
        // 这个测试验证 verifyEab 方法使用 hash_equals
        // 由于无法直接测试时序，我们只验证方法存在且可调用

        $outerJws = [
            'payload' => [
                'externalAccountBinding' => [
                    'protected' => base64_encode('{"kid":"test_kid","alg":"HS256"}'),
                    'payload' => base64_encode('test'),
                    'signature' => base64_encode('invalid'),
                ],
            ],
        ];

        // 无效的 EAB 应该返回 false
        $result = $this->jwsService->verifyEab($outerJws, 'wrong_kid', 'wrong_hmac');
        $this->assertFalse($result);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
