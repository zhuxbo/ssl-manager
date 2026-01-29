<?php

namespace Install\Installer;

use Install\DTO\InstallConfig;

/**
 * 环境变量配置器
 */
class EnvConfigurator
{
    private string $projectRoot;

    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 3);
    }

    /**
     * 生成并写入 .env 文件
     */
    public function configure(InstallConfig $config): bool
    {
        $envExample = $this->projectRoot.'/.env.example';
        $envFile = $this->projectRoot.'/.env';

        if (! file_exists($envExample)) {
            return false;
        }

        $template = file_get_contents($envExample);

        // 替换数据库配置
        $template = preg_replace('/DB_HOST=.*/', 'DB_HOST='.$config->dbHost, $template);
        $template = preg_replace('/DB_PORT=.*/', 'DB_PORT='.$config->dbPort, $template);
        $template = preg_replace('/DB_DATABASE=.*/', 'DB_DATABASE='.$config->dbDatabase, $template);
        $template = preg_replace('/DB_USERNAME=.*/', 'DB_USERNAME='.$config->dbUsername, $template);
        $template = preg_replace('/DB_PASSWORD=.*/', 'DB_PASSWORD='.$config->dbPassword, $template);

        // 设置 DB_COLLATION
        $collation = $config->dbCollation ?: 'utf8mb4_unicode_520_ci';
        if (str_contains($template, 'DB_COLLATION=')) {
            $template = preg_replace('/DB_COLLATION=.*/', 'DB_COLLATION='.$collation, $template);
        } else {
            $template = preg_replace('/(DB_PASSWORD=.*)/', "$1\nDB_COLLATION=".$collation, $template);
        }

        // 替换 Redis 配置
        $template = preg_replace('/REDIS_HOST=.*/', 'REDIS_HOST='.$config->redisHost, $template);
        $template = preg_replace('/REDIS_PORT=.*/', 'REDIS_PORT='.$config->redisPort, $template);

        // 设置 Redis 认证信息
        if (str_contains($template, 'REDIS_USERNAME=')) {
            $template = preg_replace('/REDIS_USERNAME=.*/', 'REDIS_USERNAME='.$config->redisUsername, $template);
        } else {
            $template = preg_replace('/(REDIS_PORT=.*)/', "$1\nREDIS_USERNAME=".$config->redisUsername, $template);
        }

        if (str_contains($template, 'REDIS_PASSWORD=')) {
            $template = preg_replace('/REDIS_PASSWORD=.*/', 'REDIS_PASSWORD='.$config->redisPassword, $template);
        } else {
            $template = preg_replace('/(REDIS_USERNAME=.*)/', "$1\nREDIS_PASSWORD=".$config->redisPassword, $template);
        }

        // 设置 APP_URL
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        $appUrl = $host ? 'https://'.$host : '';
        $template = preg_replace('/APP_URL=.*/', 'APP_URL='.$appUrl, $template);

        return file_put_contents($envFile, $template) !== false;
    }

    /**
     * 检查 .env 文件是否存在
     */
    public function envExists(): bool
    {
        return file_exists($this->projectRoot.'/.env');
    }

    /**
     * 检查 .env.example 文件是否存在
     */
    public function envExampleExists(): bool
    {
        return file_exists($this->projectRoot.'/.env.example');
    }
}
