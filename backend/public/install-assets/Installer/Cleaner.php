<?php

namespace Install\Installer;

/**
 * 安装文件清理器
 */
class Cleaner
{
    private string $publicDir;

    private string $projectRoot;

    public function __construct(?string $publicDir = null, ?string $projectRoot = null)
    {
        $this->publicDir = $publicDir ?? dirname(__DIR__, 2);
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 3);
    }

    /**
     * 创建清理脚本并在后台执行
     */
    public function scheduleCleanup(): bool
    {
        $installDir = $this->publicDir . '/install-assets';
        $installFile = $this->publicDir . '/install.php';
        $cleanupScript = $this->projectRoot . '/cleanup_install.php';

        $cleanupContent = <<<PHP
<?php
// 延迟3秒后清理安装文件
sleep(3);

// 删除安装资源目录
if (is_dir("$installDir")) {
    function deleteDirectory(\$dir) {
        if (!is_dir(\$dir)) return false;
        \$files = array_diff(scandir(\$dir), array(".", ".."));
        foreach (\$files as \$file) {
            \$path = \$dir . DIRECTORY_SEPARATOR . \$file;
            is_dir(\$path) ? deleteDirectory(\$path) : unlink(\$path);
        }
        return rmdir(\$dir);
    }
    deleteDirectory("$installDir");
}

// 删除安装主文件
if (file_exists("$installFile")) {
    unlink("$installFile");
}

// 删除自身
unlink(__FILE__);
PHP;

        if (file_put_contents($cleanupScript, $cleanupContent) === false) {
            return false;
        }

        // 在后台执行清理脚本
        if (function_exists('exec')) {
            exec("php $cleanupScript > /dev/null 2>&1 &");

            return true;
        }

        return false;
    }

    /**
     * 立即清理安装文件
     */
    public function cleanNow(): bool
    {
        $installDir = $this->publicDir . '/install-assets';
        $installFile = $this->publicDir . '/install.php';

        $success = true;

        // 删除安装资源目录
        if (is_dir($installDir)) {
            $success = $this->deleteDirectory($installDir) && $success;
        }

        // 删除安装主文件
        if (file_exists($installFile)) {
            $success = unlink($installFile) && $success;
        }

        return $success;
    }

    /**
     * 递归删除目录
     */
    private function deleteDirectory(string $dir): bool
    {
        if (! is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }

        return rmdir($dir);
    }
}
