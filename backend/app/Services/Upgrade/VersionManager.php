<?php

namespace App\Services\Upgrade;

use Illuminate\Support\Facades\Config;

class VersionManager
{
    /**
     * 获取当前版本信息
     */
    public function getCurrentVersion(): array
    {
        return [
            'version' => Config::get('version.version', '0.0.0'),
            'name' => Config::get('version.name', 'SSL Manager'),
            'build_time' => Config::get('version.build_time', ''),
            'build_commit' => Config::get('version.build_commit', ''),
            'channel' => Config::get('version.channel', 'main'),
        ];
    }

    /**
     * 获取当前版本号
     */
    public function getVersionString(): string
    {
        return Config::get('version.version', '0.0.0');
    }

    /**
     * 获取当前发布通道
     */
    public function getChannel(): string
    {
        return Config::get('version.channel', 'main');
    }

    /**
     * 比较两个语义化版本
     *
     * @return int -1 if v1 < v2, 0 if v1 == v2, 1 if v1 > v2
     */
    public function compareVersions(string $v1, string $v2): int
    {
        // 移除 v 前缀
        $v1 = ltrim($v1, 'vV');
        $v2 = ltrim($v2, 'vV');

        // 分离版本号和预发布标识
        $v1Parts = $this->parseVersion($v1);
        $v2Parts = $this->parseVersion($v2);

        // 比较主版本号
        $result = version_compare($v1Parts['version'], $v2Parts['version']);
        if ($result !== 0) {
            return $result;
        }

        // 版本号相同时比较预发布标识
        // 没有预发布标识的版本 > 有预发布标识的版本
        if (empty($v1Parts['prerelease']) && ! empty($v2Parts['prerelease'])) {
            return 1;
        }
        if (! empty($v1Parts['prerelease']) && empty($v2Parts['prerelease'])) {
            return -1;
        }
        if (! empty($v1Parts['prerelease']) && ! empty($v2Parts['prerelease'])) {
            return strcmp($v1Parts['prerelease'], $v2Parts['prerelease']);
        }

        return 0;
    }

    /**
     * 解析版本号
     */
    protected function parseVersion(string $version): array
    {
        // 匹配 1.0.0、1.0.0-dev、1.0.0-alpha.1 等格式
        if (preg_match('/^(\d+\.\d+\.\d+)(?:-(.+))?$/', $version, $matches)) {
            return [
                'version' => $matches[1],
                'prerelease' => $matches[2] ?? '',
            ];
        }

        return [
            'version' => $version,
            'prerelease' => '',
        ];
    }

    /**
     * 检查是否允许升级到目标版本
     */
    public function isUpgradeAllowed(string $targetVersion): bool
    {
        $currentVersion = $this->getVersionString();
        $allowDowngrade = Config::get('upgrade.constraints.allow_downgrade', false);

        // 比较版本
        $comparison = $this->compareVersions($targetVersion, $currentVersion);

        // 如果目标版本比当前版本旧，且不允许降级
        if ($comparison < 0 && ! $allowDowngrade) {
            return false;
        }

        // 如果版本相同，不需要升级
        if ($comparison === 0) {
            return false;
        }

        return true;
    }

    /**
     * 检查 PHP 版本是否满足最低要求
     */
    public function checkPhpVersion(): bool
    {
        $minVersion = Config::get('upgrade.constraints.min_php_version', '8.3.0');

        return version_compare(PHP_VERSION, $minVersion, '>=');
    }

    /**
     * 获取最低 PHP 版本要求
     */
    public function getMinPhpVersion(): string
    {
        return Config::get('upgrade.constraints.min_php_version', '8.3.0');
    }
}
