<?php

namespace Tests\Unit\Services\Delegation;

use App\Services\Delegation\CnameDelegationService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * CnameDelegationService 静态方法测试（不需要数据库）
 */
class CnameDelegationServiceStaticTest extends TestCase
{
    // ==================== getDelegationPrefixForCa ====================

    #[DataProvider('caPrefixProvider')]
    public function test_get_delegation_prefix_for_ca(string $ca, string $expected): void
    {
        $this->assertEquals($expected, CnameDelegationService::getDelegationPrefixForCa($ca));
    }

    public static function caPrefixProvider(): array
    {
        return [
            'Sectigo' => ['Sectigo', '_pki-validation'],
            'sectigo小写' => ['sectigo', '_pki-validation'],
            'Comodo' => ['Comodo', '_pki-validation'],
            'Certum' => ['Certum', '_certum'],
            'DigiCert' => ['DigiCert', '_dnsauth'],
            'digicert小写' => ['digicert', '_dnsauth'],
            'GeoTrust' => ['GeoTrust', '_dnsauth'],
            'Thawte' => ['Thawte', '_dnsauth'],
            'RapidSSL' => ['RapidSSL', '_dnsauth'],
            'Symantec' => ['Symantec', '_dnsauth'],
            'TrustAsia' => ['TrustAsia', '_dnsauth'],
            'LetsEncrypt' => ['LetsEncrypt', '_acme-challenge'],
            'ZeroSSL' => ['ZeroSSL', '_acme-challenge'],
            '未知CA' => ['Unknown', '_acme-challenge'],
        ];
    }
}
