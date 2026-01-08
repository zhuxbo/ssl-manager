<?php

namespace Install\DTO;

/**
 * 安装配置数据对象
 */
class InstallConfig
{
    public function __construct(
        public string $dbHost = '127.0.0.1',
        public int $dbPort = 3306,
        public string $dbDatabase = '',
        public string $dbUsername = '',
        public string $dbPassword = '',
        public string $dbCollation = '',
        public ?string $mysqlVersion = null,
        public ?float $mysqlMajorVersion = null,
        public string $redisHost = '127.0.0.1',
        public int $redisPort = 6379,
        public string $redisUsername = '',
        public string $redisPassword = '',
    ) {}

    /**
     * 从 Session 恢复配置
     */
    public static function fromSession(): self
    {
        $data = $_SESSION['install_config'] ?? [];

        return new self(
            dbHost: $data['db_host'] ?? '127.0.0.1',
            dbPort: (int) ($data['db_port'] ?? 3306),
            dbDatabase: $data['db_database'] ?? '',
            dbUsername: $data['db_username'] ?? '',
            dbPassword: $data['db_password'] ?? '',
            dbCollation: $data['db_collation'] ?? '',
            mysqlVersion: $data['mysql_version'] ?? null,
            mysqlMajorVersion: isset($data['mysql_major_version']) ? (float) $data['mysql_major_version'] : null,
            redisHost: $data['redis_host'] ?? '127.0.0.1',
            redisPort: (int) ($data['redis_port'] ?? 6379),
            redisUsername: $data['redis_username'] ?? '',
            redisPassword: $data['redis_password'] ?? '',
        );
    }

    /**
     * 从 POST 请求创建配置
     */
    public static function fromPost(): self
    {
        $config = self::fromSession();

        if (isset($_POST['db_host'])) {
            $config->dbHost = trim($_POST['db_host']);
        }
        if (isset($_POST['db_port'])) {
            $config->dbPort = (int) trim($_POST['db_port']);
        }
        if (isset($_POST['db_database'])) {
            $config->dbDatabase = trim($_POST['db_database']);
        }
        if (isset($_POST['db_username'])) {
            $config->dbUsername = trim($_POST['db_username']);
        }
        if (isset($_POST['db_password'])) {
            $config->dbPassword = trim($_POST['db_password']);
        }
        if (isset($_POST['redis_host'])) {
            $config->redisHost = trim($_POST['redis_host']);
        }
        if (isset($_POST['redis_port'])) {
            $config->redisPort = (int) trim($_POST['redis_port']);
        }
        if (isset($_POST['redis_username'])) {
            $config->redisUsername = trim($_POST['redis_username']);
        }
        if (isset($_POST['redis_password'])) {
            $config->redisPassword = trim($_POST['redis_password']);
        }

        return $config;
    }

    /**
     * 保存到 Session
     */
    public function toSession(): void
    {
        $_SESSION['install_config'] = $this->toArray();
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'db_host' => $this->dbHost,
            'db_port' => $this->dbPort,
            'db_database' => $this->dbDatabase,
            'db_username' => $this->dbUsername,
            'db_password' => $this->dbPassword,
            'db_collation' => $this->dbCollation,
            'mysql_version' => $this->mysqlVersion,
            'mysql_major_version' => $this->mysqlMajorVersion,
            'redis_host' => $this->redisHost,
            'redis_port' => $this->redisPort,
            'redis_username' => $this->redisUsername,
            'redis_password' => $this->redisPassword,
        ];
    }

    /**
     * 验证数据库配置是否完整
     */
    public function isDatabaseConfigComplete(): bool
    {
        return ! empty($this->dbHost)
            && ! empty($this->dbDatabase)
            && ! empty($this->dbUsername);
    }
}
