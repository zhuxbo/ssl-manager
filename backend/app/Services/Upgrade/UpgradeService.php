<?php

namespace App\Services\Upgrade;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class UpgradeService
{
    public function __construct(
        protected VersionManager $versionManager,
        protected ReleaseClient $releaseClient,
        protected BackupManager $backupManager,
        protected PackageExtractor $packageExtractor,
    ) {}

    /**
     * 检查更新
     */
    public function checkForUpdate(): array
    {
        $currentVersion = $this->versionManager->getCurrentVersion();
        $channel = $currentVersion['channel'];

        $latestRelease = $this->releaseClient->getLatestRelease($channel);

        if (! $latestRelease) {
            return [
                'has_update' => false,
                'current_version' => $currentVersion['version'],
                'latest_version' => null,
                'message' => '无法获取最新版本信息',
            ];
        }

        $comparison = $this->versionManager->compareVersions(
            $latestRelease['version'],
            $currentVersion['version']
        );

        $hasUpdate = $comparison > 0;

        return [
            'has_update' => $hasUpdate,
            'current_version' => $currentVersion['version'],
            'latest_version' => $latestRelease['version'],
            'changelog' => $latestRelease['body'] ?? '',
            'download_url' => $this->releaseClient->findUpgradePackageUrl($latestRelease),
            'package_size' => $this->formatPackageSize($latestRelease),
            'release_date' => $latestRelease['published_at'] ?? '',
            'channel' => $channel,
        ];
    }

    /**
     * 执行升级
     */
    public function performUpgrade(string $version = 'latest'): array
    {
        $steps = [];

        try {
            // 步骤 1: 获取目标版本信息
            $steps[] = ['step' => 'fetch_release', 'status' => 'running'];

            if ($version === 'latest') {
                $channel = $this->versionManager->getChannel();
                $release = $this->releaseClient->getLatestRelease($channel);
            } else {
                $tag = str_starts_with($version, 'v') ? $version : "v$version";
                $release = $this->releaseClient->getReleaseByTag($tag);
            }

            if (! $release) {
                throw new RuntimeException("无法获取版本 $version 的信息");
            }

            $steps[count($steps) - 1]['status'] = 'completed';
            $targetVersion = $release['version'];
            $currentVersion = $this->versionManager->getVersionString();

            // 步骤 2: 检查版本
            $steps[] = ['step' => 'check_version', 'status' => 'running'];

            if ($targetVersion === $currentVersion) {
                throw new RuntimeException("当前已是最新版本 $currentVersion，无需升级");
            }

            if (! $this->versionManager->isUpgradeAllowed($targetVersion)) {
                throw new RuntimeException("不允许从 $currentVersion 升级到 $targetVersion（目标版本低于当前版本）");
            }

            $steps[count($steps) - 1]['status'] = 'completed';

            // 检查版本顺序约束（默认关闭，支持跨版本升级）
            $requireSequential = Config::get('upgrade.constraints.require_sequential', false);
            if ($requireSequential) {
                $steps[] = ['step' => 'check_sequential', 'status' => 'running'];

                // 获取可用版本列表
                $releases = $this->releaseClient->getReleaseHistory(50);
                $availableVersions = array_column($releases, 'version');

                if (! $this->versionManager->isSequentialUpgrade($targetVersion, $availableVersions)) {
                    $nextVersion = $this->versionManager->getNextUpgradeVersion($availableVersions);
                    $currentVersion = $this->versionManager->getVersionString();

                    if ($nextVersion) {
                        throw new RuntimeException(
                            "必须按版本顺序升级。当前版本: $currentVersion，下一个可升级版本: $nextVersion，" .
                            "目标版本: $targetVersion。请先升级到 $nextVersion"
                        );
                    } else {
                        throw new RuntimeException("没有可用的升级版本");
                    }
                }

                $steps[count($steps) - 1]['status'] = 'completed';
            }

            // 检查 PHP 版本
            if (! $this->versionManager->checkPhpVersion()) {
                $minVersion = $this->versionManager->getMinPhpVersion();
                throw new RuntimeException("PHP 版本不满足要求，需要 PHP >= $minVersion");
            }

            // 步骤 2: 创建备份
            $forceBackup = Config::get('upgrade.behavior.force_backup', true);
            $backupId = null;

            if ($forceBackup) {
                $steps[] = ['step' => 'backup', 'status' => 'running'];
                $backupId = $this->backupManager->createBackup();
                $steps[count($steps) - 1]['status'] = 'completed';
                $steps[count($steps) - 1]['backup_id'] = $backupId;
            }

            // 步骤 3: 进入维护模式
            $maintenanceMode = Config::get('upgrade.behavior.maintenance_mode', true);
            if ($maintenanceMode) {
                $steps[] = ['step' => 'maintenance_on', 'status' => 'running'];
                Artisan::call('down', ['--retry' => 60]);
                $steps[count($steps) - 1]['status'] = 'completed';
            }

            // 步骤 4: 下载升级包（优先 Gitee，回退 GitHub）
            $steps[] = ['step' => 'download', 'status' => 'running'];
            $packagePath = $this->packageExtractor->getDownloadPath() . "/upgrade-$targetVersion.zip";

            if (! $this->releaseClient->downloadUpgradePackage($release, $packagePath)) {
                throw new RuntimeException('下载升级包失败（所有下载源都不可用）');
            }
            $steps[count($steps) - 1]['status'] = 'completed';

            // 步骤 5: 解压并验证
            $steps[] = ['step' => 'extract', 'status' => 'running'];
            $extractedPath = $this->packageExtractor->extract($packagePath);
            $this->packageExtractor->validatePackage($extractedPath);
            $steps[count($steps) - 1]['status'] = 'completed';

            // 步骤 6: 应用升级
            $steps[] = ['step' => 'apply', 'status' => 'running'];
            $this->packageExtractor->applyUpgrade($extractedPath);
            $steps[count($steps) - 1]['status'] = 'completed';

            // 步骤 6.5: 检测并安装 Composer 依赖
            $backendDir = $this->findBackendDirInPackage($extractedPath);
            $needComposerInstall = $backendDir && (
                file_exists("$backendDir/composer.json") ||
                file_exists("$backendDir/composer.lock")
            );

            if ($needComposerInstall) {
                $steps[] = ['step' => 'composer_install', 'status' => 'running'];
                Log::info('[Upgrade] Detected composer changes, running composer install');

                $composerInstallSuccess = $this->runComposerInstall();
                if (! $composerInstallSuccess) {
                    throw new RuntimeException('Composer 依赖安装失败');
                }

                $steps[count($steps) - 1]['status'] = 'completed';
            }

            // 清理 opcache 以便加载新代码
            if (function_exists('opcache_reset')) {
                opcache_reset();
                Log::info('[Upgrade] opcache_reset called after applying upgrade');
            }

            // 步骤 7: 运行迁移
            $autoMigrate = Config::get('upgrade.behavior.auto_migrate', true);
            if ($autoMigrate) {
                $steps[] = ['step' => 'migrate', 'status' => 'running'];
                Artisan::call('migrate', ['--force' => true]);
                $steps[count($steps) - 1]['status'] = 'completed';
            }

            // 步骤 8: 运行种子
            $autoSeed = Config::get('upgrade.behavior.auto_seed', true);
            if ($autoSeed) {
                $steps[] = ['step' => 'seed', 'status' => 'running'];
                $seedClass = Config::get('upgrade.behavior.seed_class');
                $seedOptions = ['--force' => true];
                if ($seedClass) {
                    $seedOptions['--class'] = $seedClass;
                }
                Artisan::call('db:seed', $seedOptions);
                $steps[count($steps) - 1]['status'] = 'completed';
            }

            // 步骤 9: 清理缓存
            $clearCache = Config::get('upgrade.behavior.clear_cache', true);
            if ($clearCache) {
                $steps[] = ['step' => 'clear_cache', 'status' => 'running'];
                Log::info('[Upgrade] Step: clear_cache - starting optimize:clear');
                Artisan::call('optimize:clear');
                Log::info('[Upgrade] optimize:clear done, output: ' . Artisan::output());

                // 重建缓存
                Log::info('[Upgrade] Starting config:cache');
                Artisan::call('config:cache');
                Log::info('[Upgrade] config:cache done, output: ' . Artisan::output());

                Log::info('[Upgrade] Starting route:cache');
                Artisan::call('route:cache');
                Log::info('[Upgrade] route:cache done, output: ' . Artisan::output());

                // 验证路由缓存文件
                $routeCacheFile = base_path('bootstrap/cache/routes-v7.php');
                Log::info('[Upgrade] Route cache file exists: ' . (file_exists($routeCacheFile) ? 'YES' : 'NO'));

                $steps[count($steps) - 1]['status'] = 'completed';
            }

            // 步骤 10: 更新版本号（所有操作完成后再更新）
            $steps[] = ['step' => 'update_version', 'status' => 'running'];
            $this->updateEnvVersion($targetVersion);
            $steps[count($steps) - 1]['status'] = 'completed';

            // 步骤 11: 清理临时文件
            $steps[] = ['step' => 'cleanup', 'status' => 'running'];
            $this->packageExtractor->cleanup($extractedPath);
            $this->packageExtractor->cleanupOldPackages();
            $steps[count($steps) - 1]['status'] = 'completed';

            // 步骤 12: 退出维护模式
            if ($maintenanceMode) {
                $steps[] = ['step' => 'maintenance_off', 'status' => 'running'];
                Artisan::call('up');
                $steps[count($steps) - 1]['status'] = 'completed';
            }

            // 最终清理 opcache
            if (function_exists('opcache_reset')) {
                opcache_reset();
                Log::info('[Upgrade] Final opcache_reset called');
            }

            Log::info("升级完成: {$this->versionManager->getVersionString()} -> $targetVersion");

            return [
                'success' => true,
                'from_version' => $this->versionManager->getVersionString(),
                'to_version' => $targetVersion,
                'backup_id' => $backupId,
                'steps' => $steps,
            ];

        } catch (\Exception $e) {
            // 标记当前步骤失败
            if (! empty($steps)) {
                $steps[count($steps) - 1]['status'] = 'failed';
                $steps[count($steps) - 1]['error'] = $e->getMessage();
            }

            // 尝试恢复
            try {
                // 退出维护模式
                Artisan::call('up');
            } catch (\Exception $upError) {
                Log::error("退出维护模式失败: {$upError->getMessage()}");
            }

            Log::error("升级失败: {$e->getMessage()}");

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'steps' => $steps,
            ];
        }
    }

    /**
     * 执行升级（带状态管理，用于后台任务）
     */
    public function performUpgradeWithStatus(string $version, UpgradeStatusManager $statusManager): array
    {
        $currentVersion = $this->versionManager->getVersionString();
        $inMaintenanceMode = false;

        try {
            // 步骤 1: 获取目标版本信息
            $statusManager->startStep('fetch_release');

            if ($version === 'latest') {
                $channel = $this->versionManager->getChannel();
                $release = $this->releaseClient->getLatestRelease($channel);
            } else {
                $tag = str_starts_with($version, 'v') ? $version : "v$version";
                $release = $this->releaseClient->getReleaseByTag($tag);
            }

            if (! $release) {
                throw new RuntimeException("无法获取版本 $version 的信息");
            }

            $statusManager->completeStep('fetch_release');
            $targetVersion = $release['version'];

            // 步骤 2: 检查版本
            $statusManager->startStep('check_version');

            if ($targetVersion === $currentVersion) {
                throw new RuntimeException("当前已是最新版本 $currentVersion，无需升级");
            }

            if (! $this->versionManager->isUpgradeAllowed($targetVersion)) {
                throw new RuntimeException("不允许从 $currentVersion 升级到 $targetVersion（目标版本低于当前版本）");
            }

            // 检查 PHP 版本
            if (! $this->versionManager->checkPhpVersion()) {
                $minVersion = $this->versionManager->getMinPhpVersion();
                throw new RuntimeException("PHP 版本不满足要求，需要 PHP >= $minVersion");
            }

            $statusManager->completeStep('check_version');

            // 步骤 3: 创建备份
            $forceBackup = Config::get('upgrade.behavior.force_backup', true);
            $backupId = null;

            if ($forceBackup) {
                $statusManager->startStep('backup');
                $backupId = $this->backupManager->createBackup();
                $statusManager->completeStep('backup');
            }

            // 步骤 3.5: 进入维护模式
            $maintenanceMode = Config::get('upgrade.behavior.maintenance_mode', true);

            if ($maintenanceMode) {
                $statusManager->startStep('maintenance_on');
                Artisan::call('down', ['--retry' => 60]);
                $inMaintenanceMode = true;
                $statusManager->completeStep('maintenance_on');
            }

            // 步骤 4: 下载升级包
            $statusManager->startStep('download');
            $packagePath = $this->packageExtractor->getDownloadPath() . "/upgrade-$targetVersion.zip";

            if (! $this->releaseClient->downloadUpgradePackage($release, $packagePath)) {
                throw new RuntimeException('下载升级包失败');
            }
            $statusManager->completeStep('download');

            // 步骤 5: 解压并验证
            $statusManager->startStep('extract');
            $extractedPath = $this->packageExtractor->extract($packagePath);
            $this->packageExtractor->validatePackage($extractedPath);
            $statusManager->completeStep('extract');

            // 步骤 6: 应用升级
            $statusManager->startStep('apply');
            $this->packageExtractor->applyUpgrade($extractedPath);
            $statusManager->completeStep('apply');

            // 步骤 6.5: Composer 依赖
            $backendDir = $this->findBackendDirInPackage($extractedPath);
            $needComposerInstall = $backendDir && (
                file_exists("$backendDir/composer.json") ||
                file_exists("$backendDir/composer.lock")
            );

            if ($needComposerInstall) {
                $statusManager->startStep('composer_install');
                if (! $this->runComposerInstall()) {
                    throw new RuntimeException('Composer 依赖安装失败');
                }
                $statusManager->completeStep('composer_install');
            }

            // 清理 opcache
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }

            // 步骤 7: 运行迁移
            if (Config::get('upgrade.behavior.auto_migrate', true)) {
                $statusManager->startStep('migrate');
                Artisan::call('migrate', ['--force' => true]);
                $statusManager->completeStep('migrate');
            }

            // 步骤 8: 清理缓存
            if (Config::get('upgrade.behavior.clear_cache', true)) {
                $statusManager->startStep('clear_cache');
                Artisan::call('optimize:clear');
                Artisan::call('config:cache');
                Artisan::call('route:cache');
                $statusManager->completeStep('clear_cache');
            }

            // 步骤 9: 更新版本号
            $statusManager->startStep('update_version');
            $this->updateEnvVersion($targetVersion);
            $statusManager->completeStep('update_version');

            // 步骤 10: 清理临时文件
            $statusManager->startStep('cleanup');
            $this->packageExtractor->cleanup($extractedPath);
            $this->packageExtractor->cleanupOldPackages();
            $statusManager->completeStep('cleanup');

            // 步骤 11: 退出维护模式
            if ($inMaintenanceMode) {
                $statusManager->startStep('maintenance_off');
                Artisan::call('up');
                $inMaintenanceMode = false;
                $statusManager->completeStep('maintenance_off');
            }

            // 步骤 12: 修复文件权限
            $statusManager->startStep('fix_permissions');
            $this->fixPermissions();
            $statusManager->completeStep('fix_permissions');

            // 最终清理
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }

            Log::info("升级完成: $currentVersion -> $targetVersion");
            $statusManager->complete($currentVersion, $targetVersion);

            return [
                'success' => true,
                'from_version' => $currentVersion,
                'to_version' => $targetVersion,
                'backup_id' => $backupId,
            ];

        } catch (\Exception $e) {
            // 如果在维护模式中，尝试退出
            if ($inMaintenanceMode) {
                try {
                    Artisan::call('up');
                    Log::info('[Upgrade] 升级失败后已退出维护模式');
                } catch (\Exception $upError) {
                    Log::error("退出维护模式失败: {$upError->getMessage()}");
                }
            }

            Log::error("升级失败: {$e->getMessage()}");
            $statusManager->fail($e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 回滚到指定备份
     */
    public function rollback(string $backupId): array
    {
        try {
            // 检查备份是否存在
            $backup = $this->backupManager->getBackup($backupId);
            if (! $backup) {
                throw new RuntimeException("备份不存在: $backupId");
            }

            // 进入维护模式
            Artisan::call('down', ['--retry' => 60]);

            // 恢复备份
            $this->backupManager->restoreBackup($backupId);

            // 清理 opcache 以加载恢复的代码
            if (function_exists('opcache_reset')) {
                opcache_reset();
                Log::info('[Rollback] opcache_reset called after restore');
            }

            // 清理并重建缓存
            Artisan::call('optimize:clear');
            Artisan::call('config:cache');
            Artisan::call('route:cache');

            // 最终清理 opcache
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }

            // 退出维护模式
            Artisan::call('up');

            Log::info("回滚完成: $backupId");

            return [
                'success' => true,
                'backup_id' => $backupId,
                'restored_version' => $backup['version'] ?? 'unknown',
            ];

        } catch (\Exception $e) {
            // 尝试退出维护模式
            try {
                Artisan::call('up');
            } catch (\Exception $upError) {
                // 忽略
            }

            Log::error("回滚失败: {$e->getMessage()}");

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 获取版本历史
     */
    public function getReleaseHistory(int $limit = 5): array
    {
        $channel = $this->versionManager->getChannel();

        return $this->releaseClient->getReleaseHistory($limit, $channel);
    }

    /**
     * 获取备份列表
     */
    public function getBackups(): array
    {
        return $this->backupManager->listBackups();
    }

    /**
     * 删除备份
     */
    public function deleteBackup(string $backupId): bool
    {
        return $this->backupManager->deleteBackup($backupId);
    }

    /**
     * 格式化包大小
     */
    protected function formatPackageSize(array $release): string
    {
        $assets = $release['assets'] ?? [];

        foreach ($assets as $asset) {
            $name = $asset['name'] ?? '';
            if (str_contains($name, 'upgrade') || str_contains($name, 'full')) {
                $size = $asset['size'] ?? 0;

                // Gitee API 不返回 size 字段，显示"未知"
                if ($size <= 0) {
                    return '未知';
                }

                return $this->formatBytes($size);
            }
        }

        return '未知';
    }

    /**
     * 更新版本号（config.json 和 .env）
     */
    protected function updateEnvVersion(string $version): void
    {
        // 更新 config.json（VersionManager 读取此文件）
        $configPaths = [
            base_path('config.json'),      // backend/config.json
            base_path('../config.json'),   // 项目根目录 config.json
        ];

        $updatedCount = 0;
        $errors = [];

        foreach ($configPaths as $configPath) {
            if (file_exists($configPath)) {
                $config = json_decode(file_get_contents($configPath), true) ?? [];
                $config['version'] = $version;
                $config['updated_at'] = date('Y-m-d H:i:s');

                $result = file_put_contents(
                    $configPath,
                    json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                );

                if ($result === false) {
                    $errors[] = $configPath;
                    Log::error("更新 config.json 版本号失败: $configPath");
                } else {
                    $updatedCount++;
                    Log::info("已更新 config.json 版本号: $configPath -> $version");
                }
            }
        }

        // 如果没有任何配置文件被更新，记录警告
        if ($updatedCount === 0) {
            Log::warning('[Upgrade] 没有找到可更新的 config.json 文件');
        }

        // 如果有错误，抛出异常
        if (! empty($errors)) {
            throw new RuntimeException(
                '更新版本号失败: ' . implode(', ', $errors) . '。请检查文件权限。'
            );
        }
    }

    /**
     * 格式化字节数
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $index = 0;

        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }

        return round($bytes, 2) . ' ' . $units[$index];
    }

    /**
     * 在升级包中查找后端目录
     */
    protected function findBackendDirInPackage(string $extractedPath): ?string
    {
        // 直接在解压目录下
        if (is_dir("$extractedPath/backend")) {
            return "$extractedPath/backend";
        }

        // 解压目录本身就是后端
        if (file_exists("$extractedPath/composer.json")) {
            return $extractedPath;
        }

        // 在子目录中查找（压缩包可能包含根目录）
        $dirs = glob("$extractedPath/*", GLOB_ONLYDIR);
        foreach ($dirs as $dir) {
            if (is_dir("$dir/backend")) {
                return "$dir/backend";
            }
            if (file_exists("$dir/composer.json")) {
                return $dir;
            }
        }

        return null;
    }

    /**
     * 执行 Composer Install
     */
    protected function runComposerInstall(): bool
    {
        $basePath = base_path();

        // 尝试查找 composer 命令
        $composerCmd = $this->findComposerCommand();
        if (! $composerCmd) {
            Log::error('[Upgrade] Composer not found');

            return false;
        }

        // 自动检测并切换镜像
        $mirrorConfigured = $this->configureComposerMirror($basePath, $composerCmd);

        $command = sprintf(
            'cd %s && %s install --no-dev --optimize-autoloader --no-interaction 2>&1',
            escapeshellarg($basePath),
            $composerCmd
        );

        Log::info("[Upgrade] Running: $command");

        exec($command, $output, $returnCode);

        $outputStr = implode("\n", $output);
        Log::info("[Upgrade] Composer output: $outputStr");

        // 如果配置了镜像，安装完成后恢复默认配置
        if ($mirrorConfigured) {
            $this->resetComposerMirror($basePath, $composerCmd);
        }

        if ($returnCode !== 0) {
            Log::error("[Upgrade] Composer install failed with code: $returnCode");

            return false;
        }

        Log::info('[Upgrade] Composer install completed successfully');

        return true;
    }

    /**
     * 检测网络并配置 Composer 镜像
     */
    protected function configureComposerMirror(string $basePath, string $composerCmd): bool
    {
        // 检查环境变量强制指定（使用 getenv 而非 env，因为这是运行时检查）
        $forceMirror = getenv('FORCE_CHINA_MIRROR');
        if ($forceMirror !== false) {
            if ($forceMirror === '0') {
                Log::info('[Upgrade] FORCE_CHINA_MIRROR=0, using default source');

                return false;
            }
            if ($forceMirror === '1') {
                Log::info('[Upgrade] FORCE_CHINA_MIRROR=1, forcing Aliyun mirror');

                return $this->setAliyunMirror($basePath, $composerCmd);
            }
        }

        // 检测是否能快速访问 GitHub API（composer.lock 中的 dist URL）
        $canAccessGithub = $this->checkNetworkAccess('https://api.github.com', 3);

        if ($canAccessGithub) {
            Log::info('[Upgrade] GitHub API accessible, using default source');

            return false;
        }

        return $this->setAliyunMirror($basePath, $composerCmd);
    }

    /**
     * 设置阿里云镜像
     */
    protected function setAliyunMirror(string $basePath, string $composerCmd): bool
    {
        $configCmd = sprintf(
            'cd %s && %s config repo.packagist composer https://mirrors.aliyun.com/composer/ 2>&1',
            escapeshellarg($basePath),
            $composerCmd
        );

        exec($configCmd, $output, $returnCode);

        if ($returnCode === 0) {
            Log::info('[Upgrade] Configured Aliyun composer mirror');

            return true;
        }

        Log::warning('[Upgrade] Failed to configure mirror, will use default');

        return false;
    }

    /**
     * 重置 Composer 镜像配置
     */
    protected function resetComposerMirror(string $basePath, string $composerCmd): void
    {
        $resetCmd = sprintf(
            'cd %s && %s config --unset repo.packagist 2>&1',
            escapeshellarg($basePath),
            $composerCmd
        );

        exec($resetCmd, $output, $returnCode);

        if ($returnCode === 0) {
            Log::info('[Upgrade] Reset composer mirror configuration');
        }
    }

    /**
     * 检测网络访问
     */
    protected function checkNetworkAccess(string $url, int $timeout = 3): bool
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_NOBODY => true,
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode >= 200 && $httpCode < 400;
    }

    /**
     * 查找 Composer 命令
     */
    protected function findComposerCommand(): ?string
    {
        // 检查常见的 composer 命令
        $commands = ['composer', 'composer.phar', '/usr/local/bin/composer', '/usr/bin/composer'];

        foreach ($commands as $cmd) {
            exec("which $cmd 2>/dev/null", $output, $returnCode);
            if ($returnCode === 0 && ! empty($output)) {
                return $cmd;
            }
        }

        // 检查当前目录是否有 composer.phar
        $pharPath = base_path('composer.phar');
        if (file_exists($pharPath)) {
            return "php $pharPath";
        }

        return null;
    }

    /**
     * 检测 Web 服务用户
     * Docker 环境返回 www-data，宝塔环境返回 www
     */
    protected function detectWebUser(): string
    {
        // 检测宝塔环境标志
        // 1. 存在 /www/server 目录
        // 2. 存在 www 系统用户
        // 3. 安装目录在 /www/wwwroot/ 下
        if (is_dir('/www/server')) {
            // 检查 www 用户是否存在
            if (function_exists('posix_getpwnam') && posix_getpwnam('www')) {
                return 'www';
            }
            // 回退检查
            exec('id www 2>/dev/null', $output, $returnCode);
            if ($returnCode === 0) {
                return 'www';
            }
        }

        // 检查安装目录是否在宝塔目录下
        $basePath = base_path();
        if (str_starts_with($basePath, '/www/wwwroot/')) {
            return 'www';
        }

        // 默认返回 www-data（Docker/Ubuntu/Debian 默认）
        return 'www-data';
    }

    /**
     * 修复文件权限
     * 参考 deploy/upgrade.sh 的权限修复逻辑
     */
    protected function fixPermissions(): void
    {
        $basePath = base_path();
        $webUser = $this->detectWebUser();

        Log::info("[Upgrade] 开始修复权限，Web 用户: $webUser");

        // 使用 shell 命令修复权限（需要适当的权限）
        // 注意：PHP 进程可能没有 chown 权限，这里尝试执行但不强制要求成功
        $commands = [
            // 修改所有者
            "chown -R $webUser:$webUser " . escapeshellarg($basePath) . ' 2>/dev/null',
            // 目录权限 755
            'find ' . escapeshellarg($basePath) . " -type d -exec chmod 755 {} \\; 2>/dev/null",
            // 文件权限 644
            'find ' . escapeshellarg($basePath) . " -type f -exec chmod 644 {} \\; 2>/dev/null",
        ];

        foreach ($commands as $command) {
            exec($command, $output, $returnCode);
            if ($returnCode !== 0) {
                Log::warning("[Upgrade] 权限修复命令执行失败 (可能需要 root 权限): $command");
            }
        }

        // .env 文件特殊处理（敏感文件，权限 600）
        $envFile = "$basePath/.env";
        if (file_exists($envFile)) {
            @chmod($envFile, 0600);
        }

        // storage 目录需要可写
        $storageDirs = [
            "$basePath/storage",
            "$basePath/storage/app",
            "$basePath/storage/framework",
            "$basePath/storage/framework/cache",
            "$basePath/storage/framework/sessions",
            "$basePath/storage/framework/views",
            "$basePath/storage/logs",
        ];

        foreach ($storageDirs as $dir) {
            if (is_dir($dir)) {
                @chmod($dir, 0775);
            }
        }

        // bootstrap/cache 目录需要可写
        $cacheDir = "$basePath/bootstrap/cache";
        if (is_dir($cacheDir)) {
            @chmod($cacheDir, 0775);
        }

        Log::info('[Upgrade] 权限修复完成');
    }
}
