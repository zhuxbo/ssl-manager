<?php

namespace Tests\Unit;

use App\Services\Upgrade\VersionManager;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class VersionManagerTest extends TestCase
{
    protected VersionManager $versionManager;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('version', [
            'version' => '1.0.0',
            'channel' => 'main',
        ]);
        Config::set('upgrade.constraints.allow_downgrade', false);

        $this->versionManager = new VersionManager;
    }

    public function test_get_current_version(): void
    {
        $version = $this->versionManager->getCurrentVersion();

        $this->assertIsArray($version);
        $this->assertEquals('1.0.0', $version['version']);
        $this->assertEquals('main', $version['channel']);
    }

    public function test_get_channel(): void
    {
        $this->assertEquals('main', $this->versionManager->getChannel());
    }

    public function test_get_version_string(): void
    {
        $this->assertEquals('1.0.0', $this->versionManager->getVersionString());
    }

    public function test_compare_versions_greater(): void
    {
        $this->assertEquals(1, $this->versionManager->compareVersions('2.0.0', '1.0.0'));
        $this->assertEquals(1, $this->versionManager->compareVersions('1.1.0', '1.0.0'));
        $this->assertEquals(1, $this->versionManager->compareVersions('1.0.1', '1.0.0'));
    }

    public function test_compare_versions_less(): void
    {
        $this->assertEquals(-1, $this->versionManager->compareVersions('1.0.0', '2.0.0'));
        $this->assertEquals(-1, $this->versionManager->compareVersions('1.0.0', '1.1.0'));
        $this->assertEquals(-1, $this->versionManager->compareVersions('1.0.0', '1.0.1'));
    }

    public function test_compare_versions_equal(): void
    {
        $this->assertEquals(0, $this->versionManager->compareVersions('1.0.0', '1.0.0'));
    }

    public function test_compare_versions_with_prerelease(): void
    {
        // 正式版 > 预发布版
        $this->assertEquals(1, $this->versionManager->compareVersions('1.0.0', '1.0.0-dev'));
        $this->assertEquals(-1, $this->versionManager->compareVersions('1.0.0-dev', '1.0.0'));
        // 相同预发布版本
        $this->assertEquals(0, $this->versionManager->compareVersions('1.0.0-dev', '1.0.0-dev'));
    }

    public function test_compare_versions_with_v_prefix(): void
    {
        $this->assertEquals(0, $this->versionManager->compareVersions('v1.0.0', '1.0.0'));
        $this->assertEquals(0, $this->versionManager->compareVersions('V1.0.0', 'v1.0.0'));
        $this->assertEquals(1, $this->versionManager->compareVersions('v2.0.0', 'v1.0.0'));
    }

    public function test_is_upgrade_allowed_newer_version(): void
    {
        $this->assertTrue($this->versionManager->isUpgradeAllowed('2.0.0'));
        $this->assertTrue($this->versionManager->isUpgradeAllowed('1.1.0'));
        $this->assertTrue($this->versionManager->isUpgradeAllowed('1.0.1'));
    }

    public function test_is_upgrade_allowed_same_version(): void
    {
        // 相同版本不需要升级
        $this->assertFalse($this->versionManager->isUpgradeAllowed('1.0.0'));
    }

    public function test_is_upgrade_allowed_older_version(): void
    {
        // 默认不允许降级
        $this->assertFalse($this->versionManager->isUpgradeAllowed('0.9.0'));
        $this->assertFalse($this->versionManager->isUpgradeAllowed('0.9.9'));
    }

    public function test_is_upgrade_allowed_with_downgrade_enabled(): void
    {
        Config::set('upgrade.constraints.allow_downgrade', true);

        // 允许降级时可以"升级"到旧版本
        $this->assertTrue($this->versionManager->isUpgradeAllowed('0.9.0'));
    }

    public function test_check_php_version(): void
    {
        Config::set('upgrade.constraints.min_php_version', '8.0.0');
        $this->assertTrue($this->versionManager->checkPhpVersion());

        Config::set('upgrade.constraints.min_php_version', '99.0.0');
        $this->assertFalse($this->versionManager->checkPhpVersion());
    }

    public function test_get_min_php_version(): void
    {
        Config::set('upgrade.constraints.min_php_version', '8.3.0');
        $this->assertEquals('8.3.0', $this->versionManager->getMinPhpVersion());
    }
}
