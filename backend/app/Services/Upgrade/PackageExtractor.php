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
        $rootFiles = ['composer.json', 'composer.lock'];
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
        // 使用 rsync 如果可用（不使用 --delete，只覆盖文件）
        if ($this->isRsyncAvailable()) {
            $command = sprintf(
                'rsync -a %s/ %s/',
                escapeshellarg($source),
                escapeshellarg($target)
            );
            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new RuntimeException("同步目录失败: $source -> $target");
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

        $deleted = 0;
        $files = File::files($this->downloadPath);

        foreach ($files as $file) {
            // 删除超过 7 天的文件
            if ($file->getMTime() < time() - 7 * 24 * 3600) {
                File::delete($file->getRealPath());
                $deleted++;
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
