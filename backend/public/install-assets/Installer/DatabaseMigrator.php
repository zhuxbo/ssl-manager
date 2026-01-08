<?php

namespace Install\Installer;

/**
 * 数据库迁移器
 */
class DatabaseMigrator
{
    private string $projectRoot;

    private array $output = [];

    private int $returnCode = -1;

    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 3);
    }

    /**
     * 执行数据库迁移
     */
    public function migrate(): bool
    {
        $this->output = [];
        $this->returnCode = -1;

        exec("cd " . escapeshellarg($this->projectRoot) . " && php artisan migrate --force 2>&1", $this->output, $this->returnCode);

        return $this->returnCode === 0;
    }

    /**
     * 执行数据填充
     */
    public function seed(): bool
    {
        $this->output = [];

        exec("cd " . escapeshellarg($this->projectRoot) . " && php artisan db:seed --force 2>&1", $this->output, $returnVar);

        return $returnVar === 0;
    }

    /**
     * 优化配置和路由
     */
    public function optimize(): bool
    {
        $this->output = [];

        exec("cd " . escapeshellarg($this->projectRoot) . " && php artisan config:cache && php artisan route:cache 2>&1", $this->output, $returnVar);

        return $returnVar === 0;
    }

    /**
     * 获取执行输出
     */
    public function getOutput(): array
    {
        return $this->output;
    }

    /**
     * 获取输出字符串
     */
    public function getOutputString(): string
    {
        return implode("\n", $this->output);
    }

    /**
     * 获取返回码
     */
    public function getReturnCode(): int
    {
        return $this->returnCode;
    }
}
