<?php

namespace Install\Checker;

/**
 * 系统环境检查协调器
 */
class RequirementChecker
{
    private PhpChecker $phpChecker;

    private FunctionChecker $functionChecker;

    private PermissionChecker $permissionChecker;

    private ToolChecker $toolChecker;

    public function __construct(?string $projectRoot = null)
    {
        $this->phpChecker = new PhpChecker();
        $this->functionChecker = new FunctionChecker();
        $this->permissionChecker = new PermissionChecker($projectRoot);
        $this->toolChecker = new ToolChecker();
    }

    /**
     * 执行所有检查
     */
    public function checkAll(): array
    {
        $phpVersion = $this->phpChecker->checkVersion();
        $extensions = $this->phpChecker->getExtensionsSummary();
        $requiredFunctions = $this->functionChecker->getRequiredSummary();
        $optionalFunctions = $this->functionChecker->getOptionalSummary();
        $permissions = $this->permissionChecker->getSummary();
        $composer = $this->toolChecker->checkComposer();
        $java = $this->toolChecker->checkJava();

        // 判断是否可以继续安装
        $canProceed = $phpVersion->success
            && $extensions['success']
            && $requiredFunctions['success']
            && $permissions['success']
            && $composer->success;

        // 收集所有错误
        $errors = [];
        $warnings = [];

        if (! $phpVersion->success) {
            $errors[] = $phpVersion->message;
        }

        foreach ($extensions['missing'] as $ext) {
            $errors[] = "缺少必要的 PHP 扩展: $ext";
        }

        foreach ($requiredFunctions['disabled'] as $func) {
            $errors[] = "PHP $func 函数被禁用，请在 php.ini 中启用它";
        }

        foreach ($permissions['failed'] as $dir) {
            $errors[] = "目录 $dir 不可写";
        }

        if (! $composer->success) {
            $errors[] = $composer->message;
        } elseif ($composer->status === 'warning') {
            $warnings[] = $composer->message;
        }

        // 收集警告
        foreach ($optionalFunctions['disabled'] as $func) {
            $warnings[] = "PHP $func 函数被禁用，可能影响性能";
        }

        if ($java->status === 'warning') {
            $warnings[] = $java->message;
        }

        return [
            'canProceed' => $canProceed,
            'errors' => $errors,
            'warnings' => $warnings,
            'details' => [
                'phpVersion' => $phpVersion,
                'extensions' => $extensions,
                'requiredFunctions' => $requiredFunctions,
                'optionalFunctions' => $optionalFunctions,
                'permissions' => $permissions,
                'composer' => $composer,
                'java' => $java,
            ],
        ];
    }

    /**
     * 检查系统是否满足最低要求
     */
    public function meetsMinimumRequirements(): bool
    {
        return $this->checkAll()['canProceed'];
    }

    /**
     * 获取 PHP 检查器
     */
    public function getPhpChecker(): PhpChecker
    {
        return $this->phpChecker;
    }

    /**
     * 获取函数检查器
     */
    public function getFunctionChecker(): FunctionChecker
    {
        return $this->functionChecker;
    }

    /**
     * 获取权限检查器
     */
    public function getPermissionChecker(): PermissionChecker
    {
        return $this->permissionChecker;
    }

    /**
     * 获取工具检查器
     */
    public function getToolChecker(): ToolChecker
    {
        return $this->toolChecker;
    }
}
