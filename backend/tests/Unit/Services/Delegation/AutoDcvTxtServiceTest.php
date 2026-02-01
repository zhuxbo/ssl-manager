<?php

namespace Tests\Unit\Services\Delegation;

use App\Services\Delegation\AutoDcvTxtService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use ReflectionClass;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * AutoDcvTxtService 测试
 * 部分测试需要数据库连接
 */
#[Group('database')]
class AutoDcvTxtServiceTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = DatabaseSeeder::class;

    protected AutoDcvTxtService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AutoDcvTxtService;
    }

    // ==================== handleOrder ====================

    public function test_handle_order_returns_false_when_no_cert(): void
    {
        $user = $this->createTestUser();
        $product = $this->createTestProduct();
        $order = $this->createTestOrder($user, $product);
        // 不创建证书

        $result = $this->service->handleOrder($order);

        $this->assertFalse($result);
    }

    public function test_handle_order_returns_false_when_dcv_method_not_txt(): void
    {
        $user = $this->createTestUser();
        $product = $this->createTestProduct();
        $order = $this->createTestOrder($user, $product);
        $this->createTestCert($order, [
            'dcv' => ['method' => 'http', 'dns' => ['host' => '_acme-challenge']],
        ]);

        $order->refresh();
        $result = $this->service->handleOrder($order);

        $this->assertFalse($result);
    }

    public function test_handle_order_returns_false_when_validation_empty(): void
    {
        $user = $this->createTestUser();
        $product = $this->createTestProduct();
        $order = $this->createTestOrder($user, $product);
        $this->createTestCert($order, [
            'dcv' => ['method' => 'txt', 'dns' => ['host' => '_acme-challenge']],
            'validation' => [],
        ]);

        $order->refresh();
        $result = $this->service->handleOrder($order);

        $this->assertFalse($result);
    }

    public function test_handle_order_returns_true_when_all_processed(): void
    {
        $user = $this->createTestUser();
        $product = $this->createTestProduct();
        $order = $this->createTestOrder($user, $product);
        $this->createTestCert($order, [
            'dcv' => ['method' => 'txt', 'dns' => ['host' => '_acme-challenge']],
            'validation' => [
                [
                    'host' => '_acme-challenge.example.com',
                    'domain' => 'example.com',
                    'value' => 'token123',
                    'auto_txt_written' => true,
                ],
            ],
        ]);

        $order->refresh();
        $result = $this->service->handleOrder($order);

        $this->assertTrue($result);
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
        ];
    }

    // ==================== shouldProcessDelegation ====================

    public function test_should_process_delegation_returns_false_when_no_changes(): void
    {
        $user = $this->createTestUser();
        $product = $this->createTestProduct();
        $order = $this->createTestOrder($user, $product);
        $this->createTestCert($order, [
            'dcv' => ['method' => 'txt', 'dns' => ['host' => '_acme-challenge']],
            'validation' => [
                [
                    'host' => '_acme-challenge.example.com',
                    'domain' => 'example.com',
                    'value' => 'token123',
                    'auto_txt_written' => true,
                ],
            ],
        ]);

        $order->refresh();
        $result = $this->service->shouldProcessDelegation($order);

        $this->assertFalse($result);
    }

    public function test_should_process_delegation_returns_true_when_has_changes(): void
    {
        $user = $this->createTestUser();
        $product = $this->createTestProduct();
        $order = $this->createTestOrder($user, $product);

        // 创建委托记录
        $this->createTestDelegation($user, [
            'zone' => 'example.com',
            'prefix' => '_acme-challenge',
            'valid' => true,
        ]);

        $this->createTestCert($order, [
            'dcv' => ['method' => 'txt', 'dns' => ['host' => '_acme-challenge']],
            'validation' => [
                [
                    'host' => '_acme-challenge.example.com',
                    'domain' => 'example.com',
                    'value' => 'token123',
                ],
            ],
        ]);

        $order->refresh();
        $result = $this->service->shouldProcessDelegation($order);

        $this->assertTrue($result);
    }

    // ==================== collectTxtRecords ====================

    public function test_collect_txt_records_skips_already_processed(): void
    {
        $user = $this->createTestUser();
        $product = $this->createTestProduct();
        $order = $this->createTestOrder($user, $product);
        $this->createTestCert($order, [
            'dcv' => ['method' => 'txt', 'dns' => ['host' => '_acme-challenge']],
            'validation' => [
                [
                    'host' => '_acme-challenge.example.com',
                    'domain' => 'example.com',
                    'value' => 'token123',
                    'auto_txt_written' => true,
                ],
            ],
        ]);

        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('collectTxtRecords');
        $method->setAccessible(true);

        $order->refresh();
        [$txtRecords, $updatedValidation, $hasChanges] = $method->invoke($this->service, $order);

        $this->assertEmpty($txtRecords);
        $this->assertFalse($hasChanges);
    }

    public function test_collect_txt_records_skips_incomplete_validation(): void
    {
        $user = $this->createTestUser();
        $product = $this->createTestProduct();
        $order = $this->createTestOrder($user, $product);
        $this->createTestCert($order, [
            'dcv' => ['method' => 'txt', 'dns' => ['host' => '_acme-challenge']],
            'validation' => [
                [
                    'host' => '_acme-challenge.example.com',
                    // 缺少 domain 和 value
                ],
            ],
        ]);

        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('collectTxtRecords');
        $method->setAccessible(true);

        $order->refresh();
        [$txtRecords, $updatedValidation, $hasChanges] = $method->invoke($this->service, $order);

        $this->assertEmpty($txtRecords);
        $this->assertFalse($hasChanges);
    }

    public function test_collect_txt_records_groups_by_delegation(): void
    {
        $user = $this->createTestUser();
        $product = $this->createTestProduct();
        $order = $this->createTestOrder($user, $product);

        // 创建委托记录
        $delegation = $this->createTestDelegation($user, [
            'zone' => 'example.com',
            'prefix' => '_acme-challenge',
            'valid' => true,
        ]);

        $this->createTestCert($order, [
            'dcv' => ['method' => 'txt', 'dns' => ['host' => '_acme-challenge']],
            'validation' => [
                [
                    'host' => '_acme-challenge.example.com',
                    'domain' => 'example.com',
                    'value' => 'token1',
                ],
                [
                    'host' => '_acme-challenge.example.com',
                    'domain' => 'example.com',
                    'value' => 'token2',
                ],
            ],
        ]);

        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('collectTxtRecords');
        $method->setAccessible(true);

        $order->refresh();
        [$txtRecords, $updatedValidation, $hasChanges] = $method->invoke($this->service, $order);

        $this->assertCount(1, $txtRecords); // 按 delegation 分组
        $this->assertCount(2, $txtRecords[$delegation->id]['tokens']);
        $this->assertTrue($hasChanges);
    }

    public function test_collect_txt_records_marks_delegation_id(): void
    {
        $user = $this->createTestUser();
        $product = $this->createTestProduct();
        $order = $this->createTestOrder($user, $product);

        // 创建委托记录
        $delegation = $this->createTestDelegation($user, [
            'zone' => 'example.com',
            'prefix' => '_acme-challenge',
            'valid' => true,
        ]);

        $this->createTestCert($order, [
            'dcv' => ['method' => 'txt', 'dns' => ['host' => '_acme-challenge']],
            'validation' => [
                [
                    'host' => '_acme-challenge.example.com',
                    'domain' => 'example.com',
                    'value' => 'token123',
                ],
            ],
        ]);

        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('collectTxtRecords');
        $method->setAccessible(true);

        $order->refresh();
        [$txtRecords, $updatedValidation, $hasChanges] = $method->invoke($this->service, $order);

        $this->assertEquals($delegation->id, $updatedValidation[0]['delegation_id']);
        $this->assertTrue($updatedValidation[0]['auto_txt_written']);
        $this->assertNotEmpty($updatedValidation[0]['auto_txt_written_at']);
    }

    public function test_collect_txt_records_skips_when_no_delegation_found(): void
    {
        $user = $this->createTestUser();
        $product = $this->createTestProduct();
        $order = $this->createTestOrder($user, $product);
        // 不创建委托记录

        $this->createTestCert($order, [
            'dcv' => ['method' => 'txt', 'dns' => ['host' => '_acme-challenge']],
            'validation' => [
                [
                    'host' => '_acme-challenge.example.com',
                    'domain' => 'example.com',
                    'value' => 'token123',
                ],
            ],
        ]);

        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('collectTxtRecords');
        $method->setAccessible(true);

        $order->refresh();
        [$txtRecords, $updatedValidation, $hasChanges] = $method->invoke($this->service, $order);

        $this->assertEmpty($txtRecords);
        $this->assertFalse($hasChanges);
        $this->assertArrayNotHasKey('auto_txt_written', $updatedValidation[0]);
    }
}
