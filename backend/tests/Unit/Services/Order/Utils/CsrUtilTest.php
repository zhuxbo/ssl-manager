<?php

namespace Tests\Unit\Services\Order\Utils;

use App\Services\Order\Utils\CsrUtil;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CsrUtilTest extends TestCase
{
    // ==================== generate ====================

    public function test_generate_rsa_csr(): void
    {
        $params = [
            'domains' => 'example.com',
            'encryption' => ['alg' => 'rsa', 'bits' => 2048],
        ];

        $result = CsrUtil::generate($params);

        $this->assertArrayHasKey('csr', $result);
        $this->assertArrayHasKey('private_key', $result);
        $this->assertStringContainsString('BEGIN CERTIFICATE REQUEST', $result['csr']);
        $this->assertStringContainsString('BEGIN PRIVATE KEY', $result['private_key']);
    }

    public function test_generate_rsa_4096_csr(): void
    {
        $params = [
            'domains' => 'example.com',
            'encryption' => ['alg' => 'rsa', 'bits' => 4096],
        ];

        $result = CsrUtil::generate($params);

        $this->assertArrayHasKey('csr', $result);
        $this->assertArrayHasKey('private_key', $result);

        // 验证密钥长度
        $privateKey = openssl_pkey_get_private($result['private_key']);
        $details = openssl_pkey_get_details($privateKey);
        $this->assertEquals(4096, $details['bits']);
    }

    public function test_generate_ecdsa_csr(): void
    {
        $params = [
            'domains' => 'example.com',
            'encryption' => ['alg' => 'ecdsa', 'bits' => 256],
        ];

        $result = CsrUtil::generate($params);

        $this->assertArrayHasKey('csr', $result);
        $this->assertArrayHasKey('private_key', $result);
        $this->assertStringContainsString('BEGIN CERTIFICATE REQUEST', $result['csr']);
        // PHP 8+ 使用 PKCS#8 格式，所以是 BEGIN PRIVATE KEY 而不是 BEGIN EC PRIVATE KEY
        $this->assertStringContainsString('BEGIN PRIVATE KEY', $result['private_key']);
    }

    public function test_generate_with_organization(): void
    {
        $params = [
            'domains' => 'example.com',
            'encryption' => ['alg' => 'rsa', 'bits' => 2048],
            'organization' => [
                'name' => 'Test Company',
                'country' => 'US',
                'state' => 'California',
                'city' => 'San Francisco',
            ],
        ];

        $result = CsrUtil::generate($params);

        $csrInfo = openssl_csr_get_subject($result['csr']);
        $this->assertEquals('Test Company', $csrInfo['O']);
        $this->assertEquals('US', $csrInfo['C']);
        $this->assertEquals('California', $csrInfo['ST']);
        $this->assertEquals('San Francisco', $csrInfo['L']);
    }

    // ==================== getEncryptionParams ====================

    #[DataProvider('encryptionParamsProvider')]
    public function test_get_encryption_params(array $input, array $expected): void
    {
        $result = CsrUtil::getEncryptionParams($input);

        foreach ($expected as $key => $value) {
            $this->assertEquals($value, $result[$key], "Key '$key' mismatch");
        }
    }

    public static function encryptionParamsProvider(): array
    {
        return [
            '默认值' => [
                [],
                ['alg' => 'rsa', 'bits' => 2048, 'digest_alg' => 'sha256'],
            ],
            'RSA 4096' => [
                ['encryption' => ['alg' => 'rsa', 'bits' => 4096]],
                ['alg' => 'rsa', 'bits' => 4096, 'digest_alg' => 'sha256'],
            ],
            'ECDSA 256' => [
                ['encryption' => ['alg' => 'ecdsa', 'bits' => 256]],
                ['alg' => 'ecdsa', 'curve' => 'prime256v1', 'digest_alg' => 'sha256'],
            ],
            'ECDSA 384' => [
                ['encryption' => ['alg' => 'ecdsa', 'bits' => 384]],
                ['alg' => 'ecdsa', 'curve' => 'secp384r1', 'digest_alg' => 'sha256'],
            ],
            'ECDSA 521' => [
                ['encryption' => ['alg' => 'ecdsa', 'bits' => 521]],
                ['alg' => 'ecdsa', 'curve' => 'secp521r1', 'digest_alg' => 'sha256'],
            ],
            'SHA384 摘要' => [
                ['encryption' => ['alg' => 'rsa', 'bits' => 2048, 'digest_alg' => 'sha384']],
                ['alg' => 'rsa', 'bits' => 2048, 'digest_alg' => 'sha384'],
            ],
            'CodeSign 强制 4096' => [
                ['encryption' => ['alg' => 'rsa', 'bits' => 2048], 'product' => ['product_type' => 'codesign']],
                ['alg' => 'rsa', 'bits' => 4096, 'digest_alg' => 'sha256'],
            ],
            'DocSign 强制 4096' => [
                ['encryption' => ['alg' => 'rsa', 'bits' => 2048], 'product' => ['product_type' => 'docsign']],
                ['alg' => 'rsa', 'bits' => 4096, 'digest_alg' => 'sha256'],
            ],
            '无效算法回退' => [
                ['encryption' => ['alg' => 'invalid']],
                ['alg' => 'rsa', 'bits' => 2048, 'digest_alg' => 'sha256'],
            ],
            '无效位数回退' => [
                ['encryption' => ['alg' => 'rsa', 'bits' => 1024]],
                ['alg' => 'rsa', 'bits' => 2048, 'digest_alg' => 'sha256'],
            ],
        ];
    }

    // ==================== getInfoParams ====================

    public function test_get_info_params_ssl_product(): void
    {
        $params = [
            'domains' => 'example.com,www.example.com',
            'product' => ['product_type' => 'ssl'],
            'organization' => [
                'name' => 'Test Company',
                'country' => 'CN',
                'state' => 'Beijing',
                'city' => 'Beijing',
            ],
        ];

        $result = CsrUtil::getInfoParams($params);

        $this->assertEquals('example.com', $result['commonName']);
        $this->assertEquals('Test Company', $result['organizationName']);
        $this->assertEquals('CN', $result['countryName']);
    }

    public function test_get_info_params_smime_mailbox(): void
    {
        $params = [
            'email' => 'test@example.com',
            'product' => ['product_type' => 'smime', 'code' => 'smime-mailbox'],
        ];

        $result = CsrUtil::getInfoParams($params);

        $this->assertEquals('test@example.com', $result['commonName']);
    }

    public function test_get_info_params_smime_individual(): void
    {
        $params = [
            'email' => 'test@example.com',
            'product' => ['product_type' => 'smime', 'code' => 'smime-individual'],
            'contact' => ['first_name' => 'John', 'last_name' => 'Doe'],
        ];

        $result = CsrUtil::getInfoParams($params);

        $this->assertEquals('John Doe', $result['commonName']);
    }

    public function test_get_info_params_smime_organization(): void
    {
        $params = [
            'email' => 'test@example.com',
            'product' => ['product_type' => 'smime', 'code' => 'smime-organization'],
            'organization' => ['name' => 'Test Company'],
        ];

        $result = CsrUtil::getInfoParams($params);

        $this->assertEquals('Test Company', $result['commonName']);
    }

    public function test_get_info_params_codesign(): void
    {
        $params = [
            'product' => ['product_type' => 'codesign'],
            'organization' => ['name' => 'Software Company'],
        ];

        $result = CsrUtil::getInfoParams($params);

        $this->assertEquals('Software Company', $result['commonName']);
    }

    public function test_get_info_params_default_values(): void
    {
        $params = [
            'domains' => 'example.com',
        ];

        $result = CsrUtil::getInfoParams($params);

        $this->assertEquals('CN', $result['countryName']);
        $this->assertEquals('Shanghai', $result['stateOrProvinceName']);
        $this->assertEquals('Shanghai', $result['localityName']);
    }

    public function test_get_info_params_certum_ev(): void
    {
        $params = [
            'domains' => 'example.com',
            'product' => ['brand' => 'Certum', 'validation_type' => 'EV'],
            'organization' => [
                'name' => 'Test Company',
                'country' => 'CN',
                'state' => 'Shanghai',
                'city' => 'Shanghai',
                'category' => 'Private Organization',
                'registration_number' => '123456789',
            ],
        ];

        $result = CsrUtil::getInfoParams($params);

        $this->assertEquals('CN', $result['jurisdictionCountryName']);
        $this->assertEquals('Shanghai', $result['jurisdictionStateOrProvinceName']);
        $this->assertEquals('Shanghai', $result['jurisdictionLocalityName']);
        $this->assertEquals('Private Organization', $result['businessCategory']);
        $this->assertEquals('123456789', $result['serialNumber']);
    }

    // ==================== getSMIMEType ====================

    #[DataProvider('smimeTypeProvider')]
    public function test_get_smime_type(array $product, string $expected): void
    {
        $this->assertEquals($expected, CsrUtil::getSMIMEType($product));
    }

    public static function smimeTypeProvider(): array
    {
        return [
            'mailbox' => [['code' => 'smime-mailbox-basic'], 'mailbox'],
            'individual' => [['code' => 'smime-individual-pro'], 'individual'],
            'sponsor' => [['code' => 'smime-sponsor-enterprise'], 'sponsor'],
            'organization' => [['code' => 'smime-organization'], 'organization'],
            '大写' => [['code' => 'SMIME-MAILBOX'], 'mailbox'],
            '使用api_id' => [['api_id' => 'smime-individual'], 'individual'],
            '未知类型' => [['code' => 'smime-unknown'], 'unknown'],
            '空数组' => [[], 'unknown'],
        ];
    }

    // ==================== matchKey ====================

    public function test_match_key_valid(): void
    {
        $params = [
            'domains' => 'example.com',
            'encryption' => ['alg' => 'rsa', 'bits' => 2048],
        ];

        $result = CsrUtil::generate($params);

        $this->assertTrue(CsrUtil::matchKey($result['csr'], $result['private_key']));
    }

    public function test_match_key_invalid(): void
    {
        $params1 = ['domains' => 'example.com', 'encryption' => ['alg' => 'rsa', 'bits' => 2048]];
        $params2 = ['domains' => 'other.com', 'encryption' => ['alg' => 'rsa', 'bits' => 2048]];

        $result1 = CsrUtil::generate($params1);
        $result2 = CsrUtil::generate($params2);

        $this->assertFalse(CsrUtil::matchKey($result1['csr'], $result2['private_key']));
    }

    public function test_match_key_invalid_key(): void
    {
        $params = ['domains' => 'example.com', 'encryption' => ['alg' => 'rsa', 'bits' => 2048]];
        $result = CsrUtil::generate($params);

        $this->assertFalse(CsrUtil::matchKey($result['csr'], 'invalid-key'));
    }

    public function test_match_key_invalid_csr(): void
    {
        $params = ['domains' => 'example.com', 'encryption' => ['alg' => 'rsa', 'bits' => 2048]];
        $result = CsrUtil::generate($params);

        $this->assertFalse(CsrUtil::matchKey('invalid-csr', $result['private_key']));
    }

    // ==================== checkDomain ====================

    public function test_check_domain_valid(): void
    {
        $params = [
            'domains' => 'example.com',
            'encryption' => ['alg' => 'rsa', 'bits' => 2048],
        ];

        $result = CsrUtil::generate($params);

        // 不抛出异常即为成功
        CsrUtil::checkDomain($result['csr'], 'example.com');
        $this->assertTrue(true);
    }

    public function test_check_domain_invalid(): void
    {
        $params = [
            'domains' => 'example.com',
            'encryption' => ['alg' => 'rsa', 'bits' => 2048],
        ];

        $result = CsrUtil::generate($params);

        $this->expectException(\Exception::class);
        CsrUtil::checkDomain($result['csr'], 'other.com');
    }

    // ==================== auto ====================

    public function test_auto_generate_csr(): void
    {
        $params = [
            'csr_generate' => 1,
            'domains' => 'example.com',
            'encryption' => ['alg' => 'rsa', 'bits' => 2048],
        ];

        $result = CsrUtil::auto($params);

        $this->assertArrayHasKey('csr', $result);
        $this->assertArrayHasKey('private_key', $result);
    }

    public function test_auto_with_existing_csr(): void
    {
        $generated = CsrUtil::generate([
            'domains' => 'example.com',
            'encryption' => ['alg' => 'rsa', 'bits' => 2048],
        ]);

        $params = [
            'csr_generate' => 0,
            'csr' => $generated['csr'],
            'domains' => 'example.com',
        ];

        $result = CsrUtil::auto($params);

        $this->assertEquals($generated['csr'], $result['csr']);
    }

    public function test_auto_smime_skips_domain_check(): void
    {
        $generated = CsrUtil::generate([
            'domains' => 'not-a-domain',
            'encryption' => ['alg' => 'rsa', 'bits' => 2048],
            'organization' => ['name' => 'Test'],
        ]);

        $params = [
            'csr_generate' => 0,
            'csr' => $generated['csr'],
            'domains' => 'different.com',
            'product' => ['product_type' => 'smime'],
        ];

        // 不应该抛出异常，因为 smime 跳过域名检查
        $result = CsrUtil::auto($params);
        $this->assertArrayHasKey('csr', $result);
    }

    public function test_auto_empty_csr_throws_error(): void
    {
        $params = [
            'csr_generate' => 0,
            'csr' => '',
            'domains' => 'example.com',
        ];

        $this->expectException(\Exception::class);
        CsrUtil::auto($params);
    }
}
