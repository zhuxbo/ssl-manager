<?php

namespace App\Services\Upgrade;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use ZipArchive;

class PackageExtractor
{
    protected string $downloadPath;

    public function __construct()
    {
        $this->downloadPath = Config::get('upgrade.package.download_path', storage_path('upgrades'));

        // 确保下载目录存在
        if (! File::isDirectory($this->downloadPath)) {
            File::makeDirectory($this->downloadPath, 0755, true);
        }
    }

    /**
     * 解压升级包
     *
     * @return string 解压后的目录路径
     */
    public function extract(string $packagePath): string
    {
        if (! File::exists($packagePath)) {
            throw new RuntimeException("升级包不存在: $packagePath");
        }

        $extractDir = $this->downloadPath . '/extract_' . uniqid();
        File::makeDirectory($extractDir, 0755, true);

        $zip = new ZipArchive;
        $result = $zip->open($packagePath);

        if ($result !== true) {
            File::deleteDirectory($extractDir);
            throw new RuntimeException("无法打开升级包: 错误码 $result");
        }

        if (! $zip->extractTo($extractDir)) {
            $zip->close();
            File::deleteDirectory($extractDir);
            throw new RuntimeException('解压升级包失败');
        }

        $zip->close();

        Log::info("升级包已解压到: $extractDir");

        return $extractDir;
    }

    /**
     * 验证升级包结构
     */
    public function validatePackage(string $extractedPath): bool
    {
        // 检查是否有 manifest.json
        $manifestFile = $this->findManifest($extractedPath);
        if (! $manifestFile) {
            throw new RuntimeException('升级包无效：缺少 manifest.json');
        }

        $manifest = json_decode(File::get($manifestFile), true);
        if (! $manifest) {
            throw new RuntimeException('升级包无效：manifest.json 格式错误');
        }

        // 验证必要字段
        if (empty($manifest['version'])) {
            throw new RuntimeException('升级包无效：缺少版本信息');
        }

        // 验证后端目录结构
        $backendDir = $this->findBackendDir($extractedPath);
        if ($backendDir) {
            // 检查关键目录
            $requiredDirs = ['app', 'config'];
            foreach ($requiredDirs as $dir) {
                if (! File::isDirectory("$backendDir/$dir")) {
                    throw new RuntimeException("升级包无效：缺少 $dir 目录");
                }
            }
        }

        return true;
    }

    /**
     * 应用升级
     */
    public function applyUpgrade(string $extractedPath): bool
    {
        $this->validatePackage($extractedPath);

        try {
            // 应用后端更新
            $backendDir = $this->findBackendDir($extractedPath);
            if ($backendDir) {
                $this->applyBackendUpgrade($backendDir);
            }

            // 应用前端更新（管理端）
            $frontendAdminDir = $this->findFrontendDir($extractedPath, 'admin');
            if ($frontendAdminDir) {
                $this->applyFrontendUpgrade($frontendAdminDir, 'admin');
            }

            // 应用前端更新（用户端）
            $frontendUserDir = $this->findFrontendDir($extractedPath, 'user');
            if ($frontendUserDir) {
                $this->applyFrontendUpgrade($frontendUserDir, 'user');
            }

            // 应用前端更新（简易申请端）
            $frontendEasyDir = $this->findFrontendDir($extractedPath, 'easy');
            if ($frontendEasyDir) {
                $this->applyFrontendUpgrade($frontendEasyDir, 'easy');
            }

            // 更新项目根目录的 config.json（版本信息）
            $rootConfigFile = $this->findRootConfig($extractedPath);
            if ($rootConfigFile) {
                $targetConfigFile = base_path('../config.json');
                File::copy($rootConfigFile, $targetConfigFile);
                Log::info('已更新项目根目录 config.json');
            }

            // 处理删除的文件
            $this->processDeletedFiles($extractedPath);

            // 修复文件权限
            $this->fixPermissions();

            // 清理缓存和临时文件
            $this->cleanupCacheFiles();

            Log::info('升级应用成功');

            return true;
        } catch (\Exception $e) {
            Log::error("升级应用失败: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * 应用后端升级
     */
    protected function applyBackendUpgrade(string $sourceDir): void
    {
        $targetDir = base_path();

        // 需要同步的目录
        $dirs = ['app', 'config', 'database', 'routes', 'bootstrap', 'public'];

        foreach ($dirs as $dir) {
            $sourcePath = "$sourceDir/$dir";
            $targetPath = "$targetDir/$dir";

            if (File::isDirectory($sourcePath)) {
                $this->syncDirectory($sourcePath, $targetPath);
            }
        }

        // 同步 vendor 目录（如果存在）
        $vendorSource = "$sourceDir/vendor";
        if (File::isDirectory($vendorSource)) {
            $this->syncDirectory($vendorSource, "$targetDir/vendor");
        }

        // 同步根目录文件
        $rootFiles = ['composer.json', 'composer.lock', 'config.json'];
        foreach ($rootFiles as $file) {
            $sourceFile = "$sourceDir/$file";
            $targetFile = "$targetDir/$file";
            if (File::exists($sourceFile)) {
                File::copy($sourceFile, $targetFile);
            }
        }
    }

    /**
     * 应用前端升级
     */
    protected function applyFrontendUpgrade(string $sourceDir, string $type): void
    {
        // 前端静态文件目标目录
        $targetDir = base_path("../frontend/$type");

        if (! File::isDirectory($targetDir)) {
            File::makeDirectory($targetDir, 0755, true);
        }

        $this->syncDirectory($sourceDir, $targetDir);
    }

    /**
     * 同步目录（只覆盖，不删除目标中的多余文件）
     */
    protected function syncDirectory(string $source, string $target): void
    {
        // 确保目标目录存在
        if (! File::isDirectory($target)) {
            File::makeDirectory($target, 0755, true);
        }

        // 使用 rsync 如果可用（不使用 --delete，只覆盖文件）
        if ($this->isRsyncAvailable()) {
            $command = sprintf(
                'rsync -av %s/ %s/ 2>&1',
                escapeshellarg($source),
                escapeshellarg($target)
            );
            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                $errorOutput = implode("\n", $output);
                Log::error("rsync 同步失败", [
                    'source' => $source,
                    'target' => $target,
                    'return_code' => $returnCode,
                    'output' => $errorOutput,
                ]);

                // rsync 失败时降级到 PHP 方式
                Log::info("rsync 失败，降级到 PHP 文件复制");
                $this->syncDirectoryPhp($source, $target);
            }
        } else {
            // 降级到 PHP 文件操作
            $this->syncDirectoryPhp($source, $target);
        }
    }

    /**
     * PHP 原生目录同步（只覆盖，不删除目标中的多余文件）
     */
    protected function syncDirectoryPhp(string $source, string $target): void
    {
        // 确保目标目录存在
        if (! File::isDirectory($target)) {
            File::makeDirectory($target, 0755, true);
        }

        // 复制源目录中的所有文件（覆盖已存在的）
        $files = File::allFiles($source);
        foreach ($files as $file) {
            $relativePath = $file->getRelativePathname();
            $targetFile = "$target/$relativePath";
            $targetFileDir = dirname($targetFile);

            if (! File::isDirectory($targetFileDir)) {
                File::makeDirectory($targetFileDir, 0755, true);
            }

            File::copy($file->getRealPath(), $targetFile);
        }

        // 删除空目录
        $this->removeEmptyDirectories($target);
    }

    /**
     * 删除空目录
     */
    protected function removeEmptyDirectories(string $path): void
    {
        $dirs = File::directories($path);
        foreach ($dirs as $dir) {
            $this->removeEmptyDirectories($dir);
            if (count(File::files($dir)) === 0 && count(File::directories($dir)) === 0) {
                File::deleteDirectory($dir);
            }
        }
    }

    /**
     * 修复文件权限
     */
    protected function fixPermissions(): void
    {
        $webUser = $this->detectWebUser();
        if (! $webUser) {
            Log::warning('无法检测 Web 服务器用户，跳过权限修复');
            return;
        }

        $basePath = base_path();
        $storagePath = storage_path();

        Log::info("修复文件权限，Web 用户: $webUser");

        // 修复 backend 目录权限
        $this->chownRecursive($basePath, $webUser);

        // 确保 storage 目录可写
        $this->chmodRecursive($storagePath, 0755, 0644);

        // 确保 bootstrap/cache 可写
        $cachePath = base_path('bootstrap/cache');
        if (File::isDirectory($cachePath)) {
            $this->chmodRecursive($cachePath, 0755, 0644);
        }

        Log::info('文件权限修复完成');
    }

    /**
     * 检测 Web 服务器用户
     */
    protected function detectWebUser(): ?string
    {
        // 按优先级尝试检测
        $possibleUsers = ['www-data', 'nginx', 'apache', 'www'];

        foreach ($possibleUsers as $user) {
            exec("id $user 2>/dev/null", $output, $returnCode);
            if ($returnCode === 0) {
                return $user;
            }
        }

        // 尝试从当前进程获取
        $currentUser = posix_getpwuid(posix_geteuid());
        if ($currentUser && $currentUser['name'] !== 'root') {
            return $currentUser['name'];
        }

        return null;
    }

    /**
     * 递归修改所有者
     */
    protected function chownRecursive(string $path, string $user): void
    {
        $command = sprintf('chown -R %s:%s %s 2>&1',
            escapeshellarg($user),
            escapeshellarg($user),
            escapeshellarg($path)
        );
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            Log::warning("chown 失败: $path", ['output' => implode("\n", $output)]);
        }
    }

    /**
     * 递归修改权限
     */
    protected function chmodRecursive(string $path, int $dirMode, int $fileMode): void
    {
        // 目录权限
        $command = sprintf('find %s -type d -exec chmod %o {} \; 2>&1',
            escapeshellarg($path),
            $dirMode
        );
        exec($command);

        // 文件权限
        $command = sprintf('find %s -type f -exec chmod %o {} \; 2>&1',
            escapeshellarg($path),
            $fileMode
        );
        exec($command);
    }

    /**
     * 检查 rsync 是否可用
     */
    protected function isRsyncAvailable(): bool
    {
        exec('which rsync 2>/dev/null', $output, $returnCode);

        return $returnCode === 0;
    }

    /**
     * 查找 manifest.json
     */
    protected function findManifest(string $extractedPath): ?string
    {
        // 直接在解压目录下
        $file = "$extractedPath/manifest.json";
        if (File::exists($file)) {
            return $file;
        }

        // 可能在子目录下（压缩包包含根目录）
        $dirs = File::directories($extractedPath);
        foreach ($dirs as $dir) {
            $file = "$dir/manifest.json";
            if (File::exists($file)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * 查找后端目录
     */
    protected function findBackendDir(string $extractedPath): ?string
    {
        // 直接在解压目录下
        if (File::isDirectory("$extractedPath/backend")) {
            return "$extractedPath/backend";
        }

        // 解压目录本身就是后端
        if (File::isDirectory("$extractedPath/app")) {
            return $extractedPath;
        }

        // 在子目录中查找
        $dirs = File::directories($extractedPath);
        foreach ($dirs as $dir) {
            if (File::isDirectory("$dir/backend")) {
                return "$dir/backend";
            }
            if (File::isDirectory("$dir/app")) {
                return $dir;
            }
        }

        return null;
    }

    /**
     * 查找前端目录
     */
    protected function findFrontendDir(string $extractedPath, string $type): ?string
    {
        $possiblePaths = [
            "$extractedPath/frontend/$type",
            "$extractedPath/$type",
        ];

        // 在子目录中查找
        $dirs = File::directories($extractedPath);
        foreach ($dirs as $dir) {
            $possiblePaths[] = "$dir/frontend/$type";
            $possiblePaths[] = "$dir/$type";
        }

        foreach ($possiblePaths as $path) {
            if (File::isDirectory($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * 查找项目根目录的 config.json
     */
    protected function findRootConfig(string $extractedPath): ?string
    {
        $possiblePaths = [
            "$extractedPath/config.json",
        ];

        // 在子目录中查找（升级包可能有根目录）
        $dirs = File::directories($extractedPath);
        foreach ($dirs as $dir) {
            $possiblePaths[] = "$dir/config.json";
        }

        foreach ($possiblePaths as $path) {
            if (File::exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * 处理删除的文件
     */
    protected function processDeletedFiles(string $extractedPath): void
    {
        // 查找 deleted-files.txt
        $deletedFilesPath = $this->findDeletedFilesList($extractedPath);
        if (! $deletedFilesPath) {
            Log::info('升级包中没有 deleted-files.txt，跳过文件删除');

            return;
        }

        $content = File::get($deletedFilesPath);
        $files = array_filter(array_map('trim', explode("\n", $content)));

        if (empty($files)) {
            Log::info('deleted-files.txt 为空，跳过文件删除');

            return;
        }

        $baseDir = base_path();
        $deletedCount = 0;

        foreach ($files as $relativePath) {
            // 移除 backend/ 前缀（git diff 输出的路径包含 backend/）
            if (str_starts_with($relativePath, 'backend/')) {
                $relativePath = substr($relativePath, 8);
            }

            // 安全检查：不允许删除配置文件和用户数据
            if ($this->isSafeToDelete($relativePath)) {
                $fullPath = "$baseDir/$relativePath";
                if (File::exists($fullPath)) {
                    File::delete($fullPath);
                    $deletedCount++;
                    Log::info("删除文件: $relativePath");
                }
            } else {
                Log::warning("跳过受保护的文件: $relativePath");
            }
        }

        Log::info("共删除 $deletedCount 个文件");
    }

    /**
     * 查找 deleted-files.txt
     */
    protected function findDeletedFilesList(string $extractedPath): ?string
    {
        $possiblePaths = [
            "$extractedPath/deleted-files.txt",
        ];

        // 在子目录中查找
        $dirs = File::directories($extractedPath);
        foreach ($dirs as $dir) {
            $possiblePaths[] = "$dir/deleted-files.txt";
        }

        foreach ($possiblePaths as $path) {
            if (File::exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * 检查文件是否可以安全删除
     */
    protected function isSafeToDelete(string $relativePath): bool
    {
        // 检查路径穿越攻击
        if (str_contains($relativePath, '../') || str_contains($relativePath, '..\\')) {
            Log::warning("检测到路径穿越尝试: $relativePath");

            return false;
        }

        // 不允许删除的文件和目录
        $protected = [
            '.env',
            'storage/',
            'bootstrap/cache/',
            'vendor/',
            '.git/',
        ];

        foreach ($protected as $pattern) {
            if (str_starts_with($relativePath, $pattern) || $relativePath === rtrim($pattern, '/')) {
                return false;
            }
        }

        return true;
    }

    /**
     * 清理缓存和临时文件
     */
    protected function cleanupCacheFiles(): void
    {
        $baseDir = base_path();
        $cacheDirs = [
            "$baseDir/bootstrap/cache",
            "$baseDir/storage/framework/cache/data",
            "$baseDir/storage/framework/views",
        ];

        foreach ($cacheDirs as $dir) {
            if (File::isDirectory($dir)) {
                // 删除目录内的文件，保留 .gitkeep
                $files = File::files($dir);
                foreach ($files as $file) {
                    if ($file->getFilename() !== '.gitkeep') {
                        File::delete($file->getRealPath());
                    }
                }

                // 递归删除子目录
                $subDirs = File::directories($dir);
                foreach ($subDirs as $subDir) {
                    File::deleteDirectory($subDir);
                }

                Log::info("已清理缓存目录: $dir");
            }
        }
    }

    /**
     * 清理解压的临时文件
     */
    public function cleanup(string $extractedPath): void
    {
        if (File::isDirectory($extractedPath) && str_contains($extractedPath, 'extract_')) {
            File::deleteDirectory($extractedPath);
            Log::info("已清理临时目录: $extractedPath");
        }
    }

    /**
     * 获取下载路径
     */
    public function getDownloadPath(): string
    {
        return $this->downloadPath;
    }

    /**
     * 清理旧的升级包
     */
    public function cleanupOldPackages(): int
    {
        $autoCleanup = Config::get('upgrade.package.auto_cleanup', true);
        if (! $autoCleanup) {
            return 0;
        }

        $retentionDays = Config::get('upgrade.package.retention_days', 30);
        $deleted = 0;
        $files = File::files($this->downloadPath);

        foreach ($files as $file) {
            // 删除超过保留期限的文件
            if ($file->getMTime() < time() - $retentionDays * 24 * 3600) {
                File::delete($file->getRealPath());
                $deleted++;
                Log::info("清理过期升级包: {$file->getFilename()}");
            }
        }

        // 清理解压目录
        $dirs = File::directories($this->downloadPath);
        foreach ($dirs as $dir) {
            if (str_contains($dir, 'extract_')) {
                File::deleteDirectory($dir);
                $deleted++;
            }
        }

        return $deleted;
    }
}
