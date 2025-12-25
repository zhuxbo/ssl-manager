<?php

namespace Install\Installer;

/**
 * Composer 安装执行器
 */
class ComposerRunner
{
    private string $projectRoot;

    private array $output = [];

    private int $returnCode = -1;

    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 3);
    }

    /**
     * 执行 composer install
     */
    public function install(): bool
    {
        $this->output = [];
        $this->returnCode = -1;

        $command = 'cd ' . escapeshellarg($this->projectRoot)
            . ' && composer install --no-interaction --no-dev --optimize-autoloader --no-scripts 2>&1';

        exec($command, $this->output, $this->returnCode);

        return $this->returnCode === 0;
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
