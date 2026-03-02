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
 * ACME 渠道：无论 CA 是什么，都返回 _acme-challenge
 */
test('acme channel always returns acme challenge', function (string $ca, string $channel) {
    expect(CnameDelegationService::getDelegationPrefixForCa($ca, $channel))->toBe('_acme-challenge');
})->with([
    'Certum+acme' => ['Certum', 'acme'],
    'Sectigo+acme' => ['Sectigo', 'acme'],
    'DigiCert+acme' => ['DigiCert', 'acme'],
    '空CA+acme' => ['', 'acme'],
]);
