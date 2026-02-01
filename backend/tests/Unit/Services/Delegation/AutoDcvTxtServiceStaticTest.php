<?php

namespace Tests\Unit\Services\Delegation;

use App\Services\Delegation\AutoDcvTxtService;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

/**
 * AutoDcvTxtService 静态/纯函数测试（不需要数据库）
 */
class AutoDcvTxtServiceStaticTest extends TestCase
{
    protected AutoDcvTxtService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AutoDcvTxtService;
    }

    // ==================== allTxtRecordsProcessed ====================

    #[DataProvider('allTxtRecordsProcessedProvider')]
    public function test_all_txt_records_processed(array $validation, bool $expected): void
    {
        $result = $this->service->allTxtRecordsProcessed($validation);
        $this->assertEquals($expected, $result);
    }

    public static function allTxtRecordsProcessedProvider(): array
    {
        return [
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
        ];
    }

    // ==================== splitPrefixAndZone ====================

    #[DataProvider('splitPrefixAndZoneProvider')]
    public function test_split_prefix_and_zone(string $host, ?string $expectedPrefix, ?string $expectedZone): void
    {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('splitPrefixAndZone');
        $method->setAccessible(true);

        [$prefix, $zone] = $method->invoke($this->service, $host);

        $this->assertEquals($expectedPrefix, $prefix);
        $this->assertEquals($expectedZone, $zone);
    }

    public static function splitPrefixAndZoneProvider(): array
    {
        return [
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
        ];
    }
}
