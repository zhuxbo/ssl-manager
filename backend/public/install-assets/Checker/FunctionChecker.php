<?php

namespace Install\Checker;

use Install\DTO\CheckResult;

/**
 * PHP 函数检查器
 */
class FunctionChecker
{
    /**
     * 必需的 PHP 函数
     */
    private const REQUIRED_FUNCTIONS = [
        'exec' => '执行外部程序，对于系统运行必不可少',
        'putenv' => '设置环境变量，Laravel 应用配置需要此函数',
        'pcntl_signal' => '队列信号处理必需',
        'pcntl_alarm' => '队列超时处理必需',
    ];

    /**
     * 可选的 PHP 函数
     */
    private const OPTIONAL_FUNCTIONS = [
        'proc_open' => '提升 Composer 性能，不影响安装但可能导致 Composer 使用备用解压方式',
    ];

    /**
     * 检查所有必需的函数
     * @return CheckResult[]
     */
    public function checkRequired(): array
    {
        $results = [];

        foreach (self::REQUIRED_FUNCTIONS as $function => $description) {
            $enabled = $this->isFunctionEnabled($function);

            if ($enabled) {
                $results[] = CheckResult::success(
                    "PHP $function 函数",
                    '可用'
                );
            } else {
                $results[] = CheckResult::error(
                    "PHP $function 函数",
                    '被禁用',
                    "PHP $function 函数被禁用，请在 php.ini 中启用它（$description）"
                );
            }
        }

        return $results;
    }

    /**
     * 检查所有可选的函数
     * @return CheckResult[]
     */
    public function checkOptional(): array
    {
        $results = [];

        foreach (self::OPTIONAL_FUNCTIONS as $function => $description) {
            $enabled = $this->isFunctionEnabled($function);

            if ($enabled) {
                $results[] = CheckResult::success(
                    "PHP $function 函数",
                    '可用'
                );
            } else {
                $results[] = CheckResult::warning(
                    "PHP $function 函数",
                    '被禁用',
                    "PHP $function 函数被禁用（$description）"
                );
            }
        }

        return $results;
    }

    /**
     * 检查函数是否可用
     */
    private function isFunctionEnabled(string $function): bool
    {
        return function_exists($function)
            && ! in_array($function, array_map('trim', explode(',', ini_get('disable_functions'))));
    }

    /**
     * 获取必需函数检查的汇总结果
     */
    public function getRequiredSummary(): array
    {
        $results = $this->checkRequired();
        $allSuccess = true;
        $disabled = [];

        foreach ($results as $result) {
            if (! $result->success) {
                $allSuccess = false;
                $disabled[] = str_replace(['PHP ', ' 函数'], '', $result->name);
            }
        }

        return [
            'success' => $allSuccess,
            'results' => $results,
            'disabled' => $disabled,
        ];
    }

    /**
     * 获取可选函数检查的汇总结果
     */
    public function getOptionalSummary(): array
    {
        $results = $this->checkOptional();
        $hasWarnings = false;
        $disabled = [];

        foreach ($results as $result) {
            if ($result->status === CheckResult::STATUS_WARNING) {
                $hasWarnings = true;
                $disabled[] = str_replace(['PHP ', ' 函数'], '', $result->name);
            }
        }

        return [
            'success' => true,
            'hasWarnings' => $hasWarnings,
            'results' => $results,
            'disabled' => $disabled,
        ];
    }

    /**
     * 检查 exec 函数是否可用
     */
    public function isExecEnabled(): bool
    {
        return $this->isFunctionEnabled('exec');
    }
}
