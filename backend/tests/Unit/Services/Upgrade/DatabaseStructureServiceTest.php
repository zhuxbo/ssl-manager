<?php

use App\Services\Upgrade\DatabaseStructureService;
use Illuminate\Support\Facades\Config;

uses(Tests\TestCase::class);

beforeEach(function () {
    $this->service = new DatabaseStructureService;
});

test('is column different detects type change', function () {
    $standard = [
        'type' => 'varchar(255)',
        'nullable' => false,
        'default' => null,
        'extra' => '',
        'comment' => '',
    ];
    $current = [
        'type' => 'text',
        'nullable' => false,
        'default' => null,
        'extra' => '',
        'comment' => '',
    ];

    $reflection = new \ReflectionClass($this->service);
    $method = $reflection->getMethod('isColumnDifferent');
    $method->setAccessible(true);

    expect($method->invoke($this->service, $standard, $current))->toBeTrue();
});

test('is column different detects nullable change', function () {
    $standard = [
        'type' => 'varchar(255)',
        'nullable' => true,
        'default' => null,
        'extra' => '',
        'comment' => '',
    ];
    $current = [
        'type' => 'varchar(255)',
        'nullable' => false,
        'default' => null,
        'extra' => '',
        'comment' => '',
    ];

    $reflection = new \ReflectionClass($this->service);
    $method = $reflection->getMethod('isColumnDifferent');
    $method->setAccessible(true);

    expect($method->invoke($this->service, $standard, $current))->toBeTrue();
});

test('is column different detects extra change', function () {
    $standard = [
        'type' => 'bigint unsigned',
        'nullable' => false,
        'default' => null,
        'extra' => 'auto_increment',
        'comment' => '',
    ];
    $current = [
        'type' => 'bigint unsigned',
        'nullable' => false,
        'default' => null,
        'extra' => '',
        'comment' => '',
    ];

    $reflection = new \ReflectionClass($this->service);
    $method = $reflection->getMethod('isColumnDifferent');
    $method->setAccessible(true);

    expect($method->invoke($this->service, $standard, $current))->toBeTrue();
});

test('is column different ignores comment by default', function () {
    Config::set('upgrade.behavior.strict_comment_check', false);

    $standard = [
        'type' => 'varchar(255)',
        'nullable' => false,
        'default' => null,
        'extra' => '',
        'comment' => '用户名',
    ];
    $current = [
        'type' => 'varchar(255)',
        'nullable' => false,
        'default' => null,
        'extra' => '',
        'comment' => 'Username',
    ];

    $reflection = new \ReflectionClass($this->service);
    $method = $reflection->getMethod('isColumnDifferent');
    $method->setAccessible(true);

    expect($method->invoke($this->service, $standard, $current))->toBeFalse();
});

test('is column different checks comment when strict', function () {
    Config::set('upgrade.behavior.strict_comment_check', true);

    $standard = [
        'type' => 'varchar(255)',
        'nullable' => false,
        'default' => null,
        'extra' => '',
        'comment' => '用户名',
    ];
    $current = [
        'type' => 'varchar(255)',
        'nullable' => false,
        'default' => null,
        'extra' => '',
        'comment' => 'Username',
    ];

    $reflection = new \ReflectionClass($this->service);
    $method = $reflection->getMethod('isColumnDifferent');
    $method->setAccessible(true);

    expect($method->invoke($this->service, $standard, $current))->toBeTrue();
});

test('is index different detects column change', function () {
    $standard = [
        'unique' => true,
        'type' => 'BTREE',
        'columns' => ['email'],
        'sub_parts' => [null],
    ];
    $current = [
        'unique' => true,
        'type' => 'BTREE',
        'columns' => ['username'],
        'sub_parts' => [null],
    ];

    $reflection = new \ReflectionClass($this->service);
    $method = $reflection->getMethod('isIndexDifferent');
    $method->setAccessible(true);

    expect($method->invoke($this->service, $standard, $current))->toBeTrue();
});

test('is index different detects unique change', function () {
    $standard = [
        'unique' => true,
        'type' => 'BTREE',
        'columns' => ['email'],
        'sub_parts' => [null],
    ];
    $current = [
        'unique' => false,
        'type' => 'BTREE',
        'columns' => ['email'],
        'sub_parts' => [null],
    ];

    $reflection = new \ReflectionClass($this->service);
    $method = $reflection->getMethod('isIndexDifferent');
    $method->setAccessible(true);

    expect($method->invoke($this->service, $standard, $current))->toBeTrue();
});

test('is index different detects type change', function () {
    $standard = [
        'unique' => false,
        'type' => 'BTREE',
        'columns' => ['name'],
        'sub_parts' => [null],
    ];
    $current = [
        'unique' => false,
        'type' => 'FULLTEXT',
        'columns' => ['name'],
        'sub_parts' => [null],
    ];

    $reflection = new \ReflectionClass($this->service);
    $method = $reflection->getMethod('isIndexDifferent');
    $method->setAccessible(true);

    expect($method->invoke($this->service, $standard, $current))->toBeTrue();
});

test('is index different detects sub parts change', function () {
    $standard = [
        'unique' => false,
        'type' => 'BTREE',
        'columns' => ['content'],
        'sub_parts' => [255],
    ];
    $current = [
        'unique' => false,
        'type' => 'BTREE',
        'columns' => ['content'],
        'sub_parts' => [null],
    ];

    $reflection = new \ReflectionClass($this->service);
    $method = $reflection->getMethod('isIndexDifferent');
    $method->setAccessible(true);

    expect($method->invoke($this->service, $standard, $current))->toBeTrue();
});

test('escape default value handles single quotes', function () {
    $reflection = new \ReflectionClass($this->service);
    $method = $reflection->getMethod('escapeDefaultValue');
    $method->setAccessible(true);

    $result = $method->invoke($this->service, "it's a test");
    expect($result)->toBe("it''s a test");

    $result = $method->invoke($this->service, "value 'with' quotes");
    expect($result)->toBe("value ''with'' quotes");
});

test('escape default value handles multiple quotes', function () {
    $reflection = new \ReflectionClass($this->service);
    $method = $reflection->getMethod('escapeDefaultValue');
    $method->setAccessible(true);

    $result = $method->invoke($this->service, "'''");
    expect($result)->toBe("''''''");
});

test('generate add column with special default', function () {
    $reflection = new \ReflectionClass($this->service);
    $method = $reflection->getMethod('generateAddColumnStatement');
    $method->setAccessible(true);

    $columnDef = [
        'type' => 'varchar(255)',
        'nullable' => false,
        'default' => "it's default",
        'extra' => '',
        'comment' => "it's comment",
    ];

    $result = $method->invoke($this->service, 'test_table', 'test_column', $columnDef);

    expect($result)->toContain("DEFAULT 'it''s default'");
    expect($result)->toContain("COMMENT 'it''s comment'");
});

test('compare table structure detects modified indexes', function () {
    $standard = [
        'columns' => [],
        'indexes' => [
            'idx_email' => [
                'unique' => true,
                'type' => 'BTREE',
                'columns' => ['email'],
                'sub_parts' => [null],
            ],
        ],
        'foreign_keys' => [],
    ];
    $current = [
        'columns' => [],
        'indexes' => [
            'idx_email' => [
                'unique' => false,
                'type' => 'BTREE',
                'columns' => ['email'],
                'sub_parts' => [null],
            ],
        ],
        'foreign_keys' => [],
    ];

    $reflection = new \ReflectionClass($this->service);
    $method = $reflection->getMethod('compareTableStructure');
    $method->setAccessible(true);

    $result = $method->invoke($this->service, $standard, $current);

    expect($result)->toHaveKey('modified_indexes');
    expect($result['modified_indexes'])->toHaveKey('idx_email');
});

test('check returns correct diff structure', function () {
    // 此测试需要实际数据库连接
    // 如果数据库不可用，验证返回错误信息结构
    try {
        $result = $this->service->check();

        expect($result)->toHaveKey('has_diff');
        expect($result)->toHaveKey('diff');
        expect($result)->toHaveKey('summary');
        expect($result['has_diff'])->toBeBool();
        expect($result['diff'])->toBeArray();
        expect($result['summary'])->toBeArray();
    } catch (\Illuminate\Database\QueryException $e) {
        // 数据库连接不可用时跳过此测试
        test()->markTestSkipped('数据库连接不可用');
    }
});
