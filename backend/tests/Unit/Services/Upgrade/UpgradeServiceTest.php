<?php

namespace Tests\Unit\Services\Upgrade;

use App\Services\Upgrade\BackupManager;
use App\Services\Upgrade\PackageExtractor;
use App\Services\Upgrade\ReleaseClient;
use App\Services\Upgrade\UpgradeService;
use App\Services\Upgrade\VersionManager;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class UpgradeServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_check_for_update_returns_no_update_when_same_version(): void
    {
        $versionManager = Mockery::mock(VersionManager::class);
        $versionManager->shouldReceive('getCurrentVersion')->andReturn([
            'version' => '1.0.0',
            'channel' => 'main',
        ]);
        $versionManager->shouldReceive('compareVersions')
            ->with('1.0.0', '1.0.0')
            ->andReturn(0);

        $releaseClient = Mockery::mock(ReleaseClient::class);
        $releaseClient->shouldReceive('getLatestRelease')
            ->with('main')
            ->andReturn([
                'version' => '1.0.0',
                'body' => 'Release notes',
                'published_at' => '2026-01-01',
            ]);
        $releaseClient->shouldReceive('findUpgradePackageUrl')->andReturn(null);

        $service = new UpgradeService(
            $versionManager,
            $releaseClient,
            Mockery::mock(BackupManager::class),
            Mockery::mock(PackageExtractor::class)
        );

        $result = $service->checkForUpdate();

        $this->assertFalse($result['has_update']);
        $this->assertEquals('1.0.0', $result['current_version']);
        $this->assertEquals('1.0.0', $result['latest_version']);
    }

    public function test_check_for_update_returns_update_available(): void
    {
        $versionManager = Mockery::mock(VersionManager::class);
        $versionManager->shouldReceive('getCurrentVersion')->andReturn([
            'version' => '1.0.0',
            'channel' => 'main',
        ]);
        $versionManager->shouldReceive('compareVersions')
            ->with('1.1.0', '1.0.0')
            ->andReturn(1);

        $releaseClient = Mockery::mock(ReleaseClient::class);
        $releaseClient->shouldReceive('getLatestRelease')
            ->with('main')
            ->andReturn([
                'version' => '1.1.0',
                'body' => 'New features',
                'published_at' => '2026-01-10',
            ]);
        $releaseClient->shouldReceive('findUpgradePackageUrl')
            ->andReturn('https://example.com/upgrade.zip');

        $service = new UpgradeService(
            $versionManager,
            $releaseClient,
            Mockery::mock(BackupManager::class),
            Mockery::mock(PackageExtractor::class)
        );

        $result = $service->checkForUpdate();

        $this->assertTrue($result['has_update']);
        $this->assertEquals('1.0.0', $result['current_version']);
        $this->assertEquals('1.1.0', $result['latest_version']);
        $this->assertEquals('New features', $result['changelog']);
    }

    public function test_check_for_update_handles_no_release(): void
    {
        $versionManager = Mockery::mock(VersionManager::class);
        $versionManager->shouldReceive('getCurrentVersion')->andReturn([
            'version' => '1.0.0',
            'channel' => 'main',
        ]);

        $releaseClient = Mockery::mock(ReleaseClient::class);
        $releaseClient->shouldReceive('getLatestRelease')
            ->with('main')
            ->andReturn(null);

        $service = new UpgradeService(
            $versionManager,
            $releaseClient,
            Mockery::mock(BackupManager::class),
            Mockery::mock(PackageExtractor::class)
        );

        $result = $service->checkForUpdate();

        $this->assertFalse($result['has_update']);
        $this->assertNull($result['latest_version']);
        $this->assertEquals('无法获取最新版本信息', $result['message']);
    }

    public function test_detect_web_user_returns_www_for_baota(): void
    {
        $service = app(UpgradeService::class);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('detectWebUser');
        $method->setAccessible(true);

        $result = $method->invoke($service);

        // 在宝塔环境应返回 www
        if (is_dir('/www/server') || str_starts_with(base_path(), '/www/wwwroot/')) {
            $this->assertEquals('www', $result);
        } else {
            $this->assertEquals('www-data', $result);
        }
    }

    public function test_get_release_history_returns_releases(): void
    {
        $versionManager = Mockery::mock(VersionManager::class);
        $versionManager->shouldReceive('getChannel')->andReturn('main');

        $releases = [
            ['version' => '1.1.0', 'tag_name' => 'v1.1.0'],
            ['version' => '1.0.0', 'tag_name' => 'v1.0.0'],
        ];

        $releaseClient = Mockery::mock(ReleaseClient::class);
        $releaseClient->shouldReceive('getReleaseHistory')
            ->with(5, 'main')
            ->andReturn($releases);

        $service = new UpgradeService(
            $versionManager,
            $releaseClient,
            Mockery::mock(BackupManager::class),
            Mockery::mock(PackageExtractor::class)
        );

        $result = $service->getReleaseHistory(5);

        $this->assertCount(2, $result);
        $this->assertEquals('1.1.0', $result[0]['version']);
    }

    public function test_get_backups_returns_backup_list(): void
    {
        $backups = [
            ['id' => 'backup_1', 'created_at' => '2026-01-01'],
            ['id' => 'backup_2', 'created_at' => '2026-01-02'],
        ];

        $backupManager = Mockery::mock(BackupManager::class);
        $backupManager->shouldReceive('listBackups')->andReturn($backups);

        $service = new UpgradeService(
            Mockery::mock(VersionManager::class),
            Mockery::mock(ReleaseClient::class),
            $backupManager,
            Mockery::mock(PackageExtractor::class)
        );

        $result = $service->getBackups();

        $this->assertCount(2, $result);
    }

    public function test_rollback_fails_for_missing_backup(): void
    {
        $backupManager = Mockery::mock(BackupManager::class);
        $backupManager->shouldReceive('getBackup')
            ->with('invalid_backup')
            ->once()
            ->andReturn(null);

        $service = new UpgradeService(
            Mockery::mock(VersionManager::class),
            Mockery::mock(ReleaseClient::class),
            $backupManager,
            Mockery::mock(PackageExtractor::class)
        );

        $result = $service->rollback('invalid_backup');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('备份不存在', $result['error']);
    }

    public function test_delete_backup_calls_backup_manager(): void
    {
        $backupManager = Mockery::mock(BackupManager::class);
        $backupManager->shouldReceive('deleteBackup')
            ->with('backup_123')
            ->once()
            ->andReturn(true);

        $service = new UpgradeService(
            Mockery::mock(VersionManager::class),
            Mockery::mock(ReleaseClient::class),
            $backupManager,
            Mockery::mock(PackageExtractor::class)
        );

        $result = $service->deleteBackup('backup_123');

        $this->assertTrue($result);
    }

    public function test_find_composer_command(): void
    {
        $service = app(UpgradeService::class);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('findComposerCommand');
        $method->setAccessible(true);

        $result = $method->invoke($service);

        // 在开发环境中，composer 应该是可用的
        $this->assertNotNull($result);
    }

    public function test_fix_permissions_method_exists_and_callable(): void
    {
        $service = app(UpgradeService::class);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('fixPermissions');

        $this->assertTrue($method->isProtected());

        // 验证方法可以被调用（不实际执行，因为可能需要 root 权限）
        $method->setAccessible(true);
        $this->assertNull($method->invoke($service));
    }

    public function test_check_network_access_method(): void
    {
        $service = app(UpgradeService::class);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('checkNetworkAccess');
        $method->setAccessible(true);

        // 测试本地地址
        $result = $method->invoke($service, 'http://localhost', 1);

        // 本地可能有或没有服务运行，所以只验证方法执行不报错
        $this->assertIsBool($result);
    }
}
