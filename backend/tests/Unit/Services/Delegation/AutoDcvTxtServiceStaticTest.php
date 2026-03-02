<?php

use App\Services\Delegation\AutoDcvTxtService;

beforeEach(function () {
    $this->service = new AutoDcvTxtService;
});

// ==================== allTxtRecordsProcessed ====================

test('all txt records processed', function (array $validation, bool $expected) {
    $result = $this->service->allTxtRecordsProcessed($validation);
    expect($result)->toBe($expected);
})->with([
    '空数组' => [[], true],
    '全部已处理' => [
        [
            ['auto_txt_written' => true],
            ['auto_txt_written' => true],
        ],
        true,
    ],
    '部分已处理' => [
        [
            ['auto_txt_written' => true],
            ['auto_txt_written' => false],
        ],
        false,
    ],
    '无标记' => [
        [
            ['host' => 'example.com'],
        ],
        false,
    ],
    '标记为false' => [
        [
            ['auto_txt_written' => false],
        ],
        false,
    ],
    '单个已处理' => [
        [
            ['auto_txt_written' => true],
        ],
        true,
    ],
    '多个未处理' => [
        [
            ['host' => 'a.com'],
            ['host' => 'b.com'],
        ],
        false,
    ],
]);

// ==================== splitPrefixAndZone ====================

test('split prefix and zone', function (string $host, ?string $expectedPrefix, ?string $expectedZone) {
    $reflection = new ReflectionClass($this->service);
    $method = $reflection->getMethod('splitPrefixAndZone');
    $method->setAccessible(true);

    [$prefix, $zone] = $method->invoke($this->service, $host);

    expect($prefix)->toBe($expectedPrefix);
    expect($zone)->toBe($expectedZone);
})->with([
    '_acme-challenge' => ['_acme-challenge.example.com', '_acme-challenge', 'example.com'],
    '_dnsauth' => ['_dnsauth.example.com', '_dnsauth', 'example.com'],
    '_pki-validation' => ['_pki-validation.example.com', '_pki-validation', 'example.com'],
    '_certum' => ['_certum.example.com', '_certum', 'example.com'],
    '子域名' => ['_acme-challenge.sub.example.com', '_acme-challenge', 'sub.example.com'],
    '多级子域名' => ['_acme-challenge.a.b.example.com', '_acme-challenge', 'a.b.example.com'],
    '不支持的前缀' => ['_unknown.example.com', null, null],
    '太短' => ['_acme-challenge.com', null, null],
    '无前缀' => ['example.com', null, null],
    '大写转换' => ['_ACME-CHALLENGE.EXAMPLE.COM', '_acme-challenge', 'example.com'],
    '仅前缀' => ['_acme-challenge', null, null],
    '两级域名' => ['_acme-challenge.co', null, null],
]);
