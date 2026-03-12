<?php

use App\Services\Delegation\CnameDelegationService;

// ==================== getDelegationPrefixForCa ====================

test('get delegation prefix for ca', function (string $ca, string $expected) {
    expect(CnameDelegationService::getDelegationPrefixForCa($ca))->toBe($expected);
})->with([
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
]);

/**
 * 未知 CA 返回 _acme-challenge（ACME 使用独立表，不再通过 channel 判断）
 */
test('unknown ca returns acme challenge prefix', function (string $ca) {
    expect(CnameDelegationService::getDelegationPrefixForCa($ca))->toBe('_acme-challenge');
})->with([
    'LetsEncrypt' => ['LetsEncrypt'],
    'ZeroSSL' => ['ZeroSSL'],
    '空CA' => [''],
]);
