<?php

use App\Services\Delegation\AutoDcvTxtService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Traits\CreatesTestData;

uses(Tests\TestCase::class, CreatesTestData::class, RefreshDatabase::class)->group('database');

beforeEach(function () {
    $this->seed = true;
    $this->seeder = DatabaseSeeder::class;
    $this->service = new AutoDcvTxtService;
});

// ==================== handleOrder ====================

test('handle order returns false when no cert', function () {
    $user = $this->createTestUser();
    $product = $this->createTestProduct();
    $order = $this->createTestOrder($user, $product);
    // 不创建证书

    $result = $this->service->handleOrder($order);

    expect($result)->toBeFalse();
});

test('handle order returns false when dcv method not txt', function () {
    $user = $this->createTestUser();
    $product = $this->createTestProduct();
    $order = $this->createTestOrder($user, $product);
    $this->createTestCert($order, [
        'dcv' => ['method' => 'http', 'dns' => ['host' => '_acme-challenge']],
    ]);

    $order->refresh();
    $result = $this->service->handleOrder($order);

    expect($result)->toBeFalse();
});

test('handle order returns false when validation empty', function () {
    $user = $this->createTestUser();
    $product = $this->createTestProduct();
    $order = $this->createTestOrder($user, $product);
    $this->createTestCert($order, [
        'dcv' => ['method' => 'txt', 'dns' => ['host' => '_acme-challenge']],
        'validation' => [],
    ]);

    $order->refresh();
    $result = $this->service->handleOrder($order);

    expect($result)->toBeFalse();
});

test('handle order returns true when all processed', function () {
    $user = $this->createTestUser();
    $product = $this->createTestProduct();
    $order = $this->createTestOrder($user, $product);
    $this->createTestCert($order, [
        'dcv' => ['method' => 'txt', 'is_delegate' => true, 'dns' => ['host' => '_acme-challenge']],
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

    expect($result)->toBeTrue();
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
]);

// ==================== shouldProcessDelegation ====================

test('should process delegation returns false when no changes', function () {
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

    expect($result)->toBeFalse();
});

test('should process delegation returns true when has changes', function () {
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

    expect($result)->toBeTrue();
});

// ==================== collectTxtRecords ====================

test('collect txt records skips already processed', function () {
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

    expect($txtRecords)->toBeEmpty();
    expect($hasChanges)->toBeFalse();
});

test('collect txt records skips incomplete validation', function () {
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

    expect($txtRecords)->toBeEmpty();
    expect($hasChanges)->toBeFalse();
});

test('collect txt records uses dcv host when validation host missing', function () {
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
                // host 缺失，依赖 dcv.dns.host 回退
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

    expect($txtRecords)->toHaveCount(1);
    expect($updatedValidation[0]['delegation_id'])->toBe($delegation->id);
    expect($updatedValidation[0]['auto_txt_written'])->toBeTrue();
    expect($hasChanges)->toBeTrue();
});

test('collect txt records expands prefix only host', function () {
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
                // 仅前缀，需补全域名
                'host' => '_acme-challenge',
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

    expect($txtRecords)->toHaveCount(1);
    expect($updatedValidation[0]['delegation_id'])->toBe($delegation->id);
    expect($updatedValidation[0]['auto_txt_written'])->toBeTrue();
    expect($hasChanges)->toBeTrue();
});

test('collect txt records skips when missing host and dcv host', function () {
    $user = $this->createTestUser();
    $product = $this->createTestProduct();
    $order = $this->createTestOrder($user, $product);

    $this->createTestCert($order, [
        'dcv' => ['method' => 'txt'],
        'validation' => [
            [
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

    expect($txtRecords)->toBeEmpty();
    expect($hasChanges)->toBeFalse();
    expect($updatedValidation[0])->not->toHaveKey('auto_txt_written');
});

test('collect txt records groups by delegation', function () {
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

    expect($txtRecords)->toHaveCount(1); // 按 delegation 分组
    expect($txtRecords[$delegation->id]['tokens'])->toHaveCount(2);
    expect($hasChanges)->toBeTrue();
});

test('collect txt records marks delegation id', function () {
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

    expect($updatedValidation[0]['delegation_id'])->toBe($delegation->id);
    expect($updatedValidation[0]['auto_txt_written'])->toBeTrue();
    expect($updatedValidation[0]['auto_txt_written_at'])->not->toBeEmpty();
});

test('collect txt records skips when no delegation found', function () {
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

    expect($txtRecords)->toBeEmpty();
    expect($hasChanges)->toBeFalse();
    expect($updatedValidation[0])->not->toHaveKey('auto_txt_written');
});
