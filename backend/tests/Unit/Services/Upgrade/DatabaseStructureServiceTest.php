<?php

namespace Tests\Unit\Services\Upgrade;

use App\Services\Upgrade\DatabaseStructureService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class DatabaseStructureServiceTest extends TestCase
{
    protected DatabaseStructureService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DatabaseStructureService;
    }

    public function test_is_column_different_detects_type_change(): void
    {
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

        $this->assertTrue($method->invoke($this->service, $standard, $current));
    }

    public function test_is_column_different_detects_nullable_change(): void
    {
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

        $this->assertTrue($method->invoke($this->service, $standard, $current));
    }

    public function test_is_column_different_detects_extra_change(): void
    {
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

        $this->assertTrue($method->invoke($this->service, $standard, $current));
    }

    public function test_is_column_different_ignores_comment_by_default(): void
    {
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

        $this->assertFalse($method->invoke($this->service, $standard, $current));
    }

    public function test_is_column_different_checks_comment_when_strict(): void
    {
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

        $this->assertTrue($method->invoke($this->service, $standard, $current));
    }

    public function test_is_index_different_detects_column_change(): void
    {
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

        $this->assertTrue($method->invoke($this->service, $standard, $current));
    }

    public function test_is_index_different_detects_unique_change(): void
    {
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

        $this->assertTrue($method->invoke($this->service, $standard, $current));
    }

    public function test_is_index_different_detects_type_change(): void
    {
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

        $this->assertTrue($method->invoke($this->service, $standard, $current));
    }

    public function test_is_index_different_detects_sub_parts_change(): void
    {
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

        $this->assertTrue($method->invoke($this->service, $standard, $current));
    }

    public function test_escape_default_value_handles_single_quotes(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('escapeDefaultValue');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, "it's a test");
        $this->assertEquals("it''s a test", $result);

        $result = $method->invoke($this->service, "value 'with' quotes");
        $this->assertEquals("value ''with'' quotes", $result);
    }

    public function test_escape_default_value_handles_multiple_quotes(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('escapeDefaultValue');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, "'''");
        $this->assertEquals("''''''", $result);
    }

    public function test_generate_add_column_with_special_default(): void
    {
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

        $this->assertStringContainsString("DEFAULT 'it''s default'", $result);
        $this->assertStringContainsString("COMMENT 'it''s comment'", $result);
    }

    public function test_compare_table_structure_detects_modified_indexes(): void
    {
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

        $this->assertArrayHasKey('modified_indexes', $result);
        $this->assertArrayHasKey('idx_email', $result['modified_indexes']);
    }

    public function test_check_returns_correct_diff_structure(): void
    {
        // 此测试需要实际数据库连接
        // 如果数据库不可用，验证返回错误信息结构
        try {
            $result = $this->service->check();

            $this->assertArrayHasKey('has_diff', $result);
            $this->assertArrayHasKey('diff', $result);
            $this->assertArrayHasKey('summary', $result);
            $this->assertIsBool($result['has_diff']);
            $this->assertIsArray($result['diff']);
            $this->assertIsArray($result['summary']);
        } catch (\Illuminate\Database\QueryException $e) {
            // 数据库连接不可用时跳过此测试
            $this->markTestSkipped('数据库连接不可用');
        }
    }
}
