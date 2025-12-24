<?php

namespace Tests\Unit;

use App\Services\Upgrade\BackupManager;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class BackupManagerTest extends TestCase
{
    protected string $testBackupPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testBackupPath = storage_path('test-backups');

        Config::set('upgrade.backup.path', $this->testBackupPath);
        Config::set('upgrade.backup.max_backups', 3);
        Config::set('upgrade.backup.include', [
            'backend' => false,
            'database' => false,
            'frontend' => false,
        ]);
    }

    protected function tearDown(): void
    {
        // 清理测试备份目录
        if (File::isDirectory($this->testBackupPath)) {
            File::deleteDirectory($this->testBackupPath);
        }

        parent::tearDown();
    }

    public function test_validate_backup_id_valid(): void
    {
        $manager = new BackupManager;

        // 有效的备份 ID 应该不抛出异常
        $reflection = new \ReflectionClass($manager);
        $method = $reflection->getMethod('validateBackupId');
        $method->setAccessible(true);

        // 不抛出异常即为通过
        $method->invoke($manager, '2025-01-15_120000');
        $method->invoke($manager, '2024-12-31_235959');

        $this->assertTrue(true);
    }

    public function test_validate_backup_id_invalid_format(): void
    {
        $manager = new BackupManager;

        $reflection = new \ReflectionClass($manager);
        $method = $reflection->getMethod('validateBackupId');
        $method->setAccessible(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('无效的备份 ID');

        $method->invoke($manager, '../../../etc/passwd');
    }

    public function test_validate_backup_id_path_traversal(): void
    {
        $manager = new BackupManager;

        $reflection = new \ReflectionClass($manager);
        $method = $reflection->getMethod('validateBackupId');
        $method->setAccessible(true);

        $invalidIds = [
            '../2025-01-15_120000',
            '2025-01-15_120000/../..',
            '..\\..\\etc',
            'foo/bar',
            '',
            '2025-01-15',
            '120000',
        ];

        foreach ($invalidIds as $id) {
            try {
                $method->invoke($manager, $id);
                $this->fail("Expected exception for invalid ID: $id");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('无效的备份 ID', $e->getMessage());
            }
        }
    }

    public function test_list_backups_empty(): void
    {
        $manager = new BackupManager;

        $backups = $manager->listBackups();

        $this->assertIsArray($backups);
        $this->assertEmpty($backups);
    }

    public function test_get_backup_not_exists(): void
    {
        $manager = new BackupManager;

        $backup = $manager->getBackup('2025-01-15_120000');

        $this->assertNull($backup);
    }

    public function test_delete_backup_not_exists(): void
    {
        $manager = new BackupManager;

        $result = $manager->deleteBackup('2025-01-15_120000');

        $this->assertFalse($result);
    }

    public function test_create_backup_minimal(): void
    {
        // 禁用所有备份内容，只测试备份流程
        Config::set('upgrade.backup.include', [
            'backend' => false,
            'database' => false,
            'frontend' => false,
        ]);

        $manager = new BackupManager;
        $backupId = $manager->createBackup();

        // 验证备份 ID 格式
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}_\d{6}$/', $backupId);

        // 验证备份目录创建
        $this->assertTrue(File::isDirectory("$this->testBackupPath/$backupId"));

        // 验证备份信息文件
        $this->assertTrue(File::exists("$this->testBackupPath/$backupId/backup.json"));

        // 验证可以获取备份信息
        $info = $manager->getBackup($backupId);
        $this->assertNotNull($info);
        $this->assertEquals($backupId, $info['id']);
    }

    public function test_list_backups_sorted_by_date(): void
    {
        Config::set('upgrade.backup.include', [
            'backend' => false,
            'database' => false,
            'frontend' => false,
        ]);

        $manager = new BackupManager;

        // 创建多个备份
        $id1 = $manager->createBackup();
        sleep(1);
        $id2 = $manager->createBackup();

        $backups = $manager->listBackups();

        $this->assertCount(2, $backups);
        // 应该按时间倒序排列
        $this->assertEquals($id2, $backups[0]['id']);
        $this->assertEquals($id1, $backups[1]['id']);
    }

    public function test_clean_old_backups(): void
    {
        Config::set('upgrade.backup.max_backups', 2);
        Config::set('upgrade.backup.include', [
            'backend' => false,
            'database' => false,
            'frontend' => false,
        ]);

        $manager = new BackupManager;

        // 创建 3 个备份
        $id1 = $manager->createBackup();
        sleep(1);
        $id2 = $manager->createBackup();
        sleep(1);
        $id3 = $manager->createBackup();

        // 最老的备份应该被清理
        $backups = $manager->listBackups();
        $this->assertCount(2, $backups);

        $backupIds = array_column($backups, 'id');
        $this->assertContains($id3, $backupIds);
        $this->assertContains($id2, $backupIds);
        $this->assertNotContains($id1, $backupIds);
    }

    public function test_delete_backup(): void
    {
        Config::set('upgrade.backup.include', [
            'backend' => false,
            'database' => false,
            'frontend' => false,
        ]);

        $manager = new BackupManager;
        $backupId = $manager->createBackup();

        $this->assertTrue(File::isDirectory("$this->testBackupPath/$backupId"));

        $result = $manager->deleteBackup($backupId);

        $this->assertTrue($result);
        $this->assertFalse(File::isDirectory("$this->testBackupPath/$backupId"));
    }
}
