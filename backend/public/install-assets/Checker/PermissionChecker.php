<?php

namespace Install\Checker;

use Install\DTO\CheckResult;

/**
 * 目录权限检查器
 */
class PermissionChecker
{
    private string $projectRoot;

    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 3);
    }

    /**
     * 需要检查的目录
     */
    private function getDirectories(): array
    {
        return [
            $this->projectRoot.'/storage',
            $this->projectRoot.'/bootstrap/cache',
        ];
    }

    /**
     * 检查所有目录权限
     *
     * @return CheckResult[]
     */
    public function check(): array
    {
        $results = [];

        foreach ($this->getDirectories() as $directory) {
            $results[] = $this->checkDirectory($directory);
        }

        return $results;
    }

    /**
     * 检查单个目录权限
     */
    private function checkDirectory(string $directory): CheckResult
    {
        $name = '目录权限: '.basename($directory);

        if (! is_dir($directory)) {
            return CheckResult::error(
                $name,
                '不存在',
                "目录 $directory 不存在"
            );
        }

        if (is_writable($directory)) {
            return CheckResult::success($name, '可写');
        }

        return CheckResult::error(
            $name,
            '不可写',
            "目录 $directory 不可写，请设置正确的权限"
        );
    }

    /**
     * 获取权限检查的汇总结果
     */
    public function getSummary(): array
    {
        $results = $this->check();
        $allSuccess = true;
        $failed = [];

        foreach ($results as $result) {
            if (! $result->success) {
                $allSuccess = false;
                $failed[] = str_replace('目录权限: ', '', $result->name);
            }
        }

        return [
            'success' => $allSuccess,
            'results' => $results,
            'failed' => $failed,
        ];
    }
}
