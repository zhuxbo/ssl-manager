<?php

namespace Install\Checker;

use Install\DTO\CheckResult;

/**
 * PHP 版本和扩展检查器
 */
class PhpChecker
{
    private const REQUIRED_VERSION = '8.3.0';

    private const REQUIRED_EXTENSIONS = [
        'bcmath', 'calendar', 'fileinfo', 'gd', 'iconv', 'intl', 'json',
        'openssl', 'pcntl', 'pdo', 'pdo_mysql', 'redis', 'zip', 'mbstring', 'curl',
    ];

    private string $phpBinary = 'php';

    private bool $execEnabled;

    public function __construct()
    {
        $this->execEnabled = function_exists('exec')
            && ! in_array('exec', array_map('trim', explode(',', ini_get('disable_functions'))));

        $this->detectPhpBinary();
    }

    /**
     * 检测 PHP 二进制路径（宝塔环境）
     */
    private function detectPhpBinary(): void
    {
        // 方法1: 通过当前脚本路径特征判断
        if (str_starts_with(__DIR__, '/www/wwwroot/')) {
            $this->phpBinary = '/www/server/php/83/bin/php';

            return;
        }

        // 方法2: 通过服务器环境变量判断
        if (isset($_SERVER['DOCUMENT_ROOT']) && str_contains($_SERVER['DOCUMENT_ROOT'], '/www/wwwroot/')) {
            $this->phpBinary = '/www/server/php/83/bin/php';

            return;
        }

        // 方法3: 通过PHP配置路径判断
        if (str_contains(ini_get('include_path'), '/www/server/')) {
            $this->phpBinary = '/www/server/php/83/bin/php';

            return;
        }

        // 方法4: 如果exec可用，通过命令行检测
        if ($this->execEnabled) {
            exec('which php 2>/dev/null', $output, $returnVar);
            if ($returnVar === 0 && ! empty($output)) {
                $detectedPath = trim($output[0]);
                if (str_contains($detectedPath, '/www/server/php/')) {
                    $this->phpBinary = $detectedPath;
                }
            }
        }
    }

    /**
     * 检查 PHP 版本
     */
    public function checkVersion(): CheckResult
    {
        $currentVersion = phpversion();
        $isValid = version_compare($currentVersion, self::REQUIRED_VERSION, '>=');

        if ($isValid) {
            return CheckResult::success(
                'PHP 版本',
                $currentVersion,
                '要求 >= '.self::REQUIRED_VERSION
            );
        }

        return CheckResult::error(
            'PHP 版本',
            $currentVersion,
            'PHP 版本必须 >= '.self::REQUIRED_VERSION."，当前版本: $currentVersion"
        );
    }

    /**
     * 检查所有必需的扩展
     *
     * @return CheckResult[]
     */
    public function checkExtensions(): array
    {
        $results = [];

        foreach (self::REQUIRED_EXTENSIONS as $extension) {
            $results[] = $this->checkExtension($extension);
        }

        return $results;
    }

    /**
     * 检查单个扩展
     */
    private function checkExtension(string $extension): CheckResult
    {
        $loaded = $this->isExtensionLoaded($extension);

        if ($loaded) {
            return CheckResult::success(
                "PHP 扩展: $extension",
                '已安装'
            );
        }

        return CheckResult::error(
            "PHP 扩展: $extension",
            '未安装',
            "缺少必要的 PHP 扩展: $extension"
        );
    }

    /**
     * 使用多种方法检测扩展是否加载
     */
    private function isExtensionLoaded(string $extension): bool
    {
        // 方法1: extension_loaded
        if (extension_loaded($extension)) {
            return true;
        }

        // 方法2: 检查 get_loaded_extensions
        if (in_array($extension, get_loaded_extensions())) {
            return true;
        }

        // 方法3: 对于 PDO 相关扩展的特殊处理
        if ($extension === 'pdo_mysql' && extension_loaded('pdo')) {
            $drivers = \PDO::getAvailableDrivers();

            return in_array('mysql', $drivers);
        }

        // 方法4: 使用 PHP CLI 检测
        if ($this->execEnabled) {
            exec("$this->phpBinary -r \"echo extension_loaded('$extension') ? 'yes' : 'no';\" 2>&1", $output, $returnVar);
            if ($returnVar === 0 && isset($output[0]) && $output[0] === 'yes') {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取所有扩展检查的汇总结果
     */
    public function getExtensionsSummary(): array
    {
        $results = $this->checkExtensions();
        $allSuccess = true;
        $missing = [];

        foreach ($results as $result) {
            if (! $result->success) {
                $allSuccess = false;
                $missing[] = str_replace('PHP 扩展: ', '', $result->name);
            }
        }

        return [
            'success' => $allSuccess,
            'results' => $results,
            'missing' => $missing,
        ];
    }
}
