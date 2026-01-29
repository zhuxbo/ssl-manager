<?php

namespace Install\Installer;

/**
 * 密钥生成器
 */
class KeyGenerator
{
    private string $projectRoot;

    private array $output = [];

    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 3);
    }

    /**
     * 生成应用密钥
     */
    public function generateAppKey(): bool
    {
        $this->output = [];

        exec('cd '.escapeshellarg($this->projectRoot).' && php artisan key:generate --force 2>&1', $this->output, $returnVar);

        return $returnVar === 0;
    }

    /**
     * 生成 JWT 密钥
     */
    public function generateJwtSecret(): bool
    {
        $this->output = [];

        exec('cd '.escapeshellarg($this->projectRoot).' && php artisan jwt:secret --force 2>&1', $this->output, $returnVar);

        return $returnVar === 0;
    }

    /**
     * 获取执行输出
     */
    public function getOutput(): array
    {
        return $this->output;
    }
}
