<?php

namespace Install\Connector;

use Install\DTO\InstallConfig;
use PDO;
use PDOException;

/**
 * 数据库连接器
 */
class DatabaseConnector
{
    private ?PDO $pdo = null;

    private ?string $error = null;

    /**
     * 测试数据库连接
     */
    public function test(InstallConfig $config): bool
    {
        $this->error = null;

        if (! $config->isDatabaseConfigComplete()) {
            $this->error = '数据库配置不完整，请填写必要的数据库信息';

            return false;
        }

        try {
            $dsn = "mysql:host=$config->dbHost;port=$config->dbPort;dbname=$config->dbDatabase";
            $this->pdo = new PDO($dsn, $config->dbUsername, $config->dbPassword, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);

            return true;
        } catch (PDOException $e) {
            $this->error = '无法连接到数据库: '.$e->getMessage();

            // 尝试测试服务器是否可访问
            $this->testNetworkConnectivity($config);

            return false;
        }
    }

    /**
     * 测试网络连通性
     */
    private function testNetworkConnectivity(InstallConfig $config): void
    {
        try {
            $socket = @fsockopen($config->dbHost, $config->dbPort, $errNo, $errStr, 3);
            if (! $socket) {
                $this->error .= " (网络不通: $errNo - $errStr)";
            } else {
                fclose($socket);
                $this->error .= ' (网络通，但认证失败，请检查用户名/密码或数据库名)';
            }
        } catch (\Exception $e) {
            $this->error .= ' (网络测试失败: '.$e->getMessage().')';
        }
    }

    /**
     * 获取 MySQL 版本信息并设置适合的 collation
     */
    public function detectVersion(InstallConfig $config): void
    {
        if ($this->pdo === null) {
            return;
        }

        try {
            $result = $this->pdo->query('SELECT VERSION()')->fetch();
            $version = $result[0];

            // 提取主版本号
            preg_match('/(\d+\.\d+)/', $version, $matches);
            $majorVersion = (float) ($matches[1] ?? 5.7);

            $config->mysqlVersion = $version;
            $config->mysqlMajorVersion = $majorVersion;

            // 根据版本设置适合的排序规则
            if ($majorVersion >= 8.0) {
                $config->dbCollation = 'utf8mb4_0900_ai_ci';
            } else {
                $config->dbCollation = 'utf8mb4_unicode_520_ci';
            }
        } catch (PDOException $e) {
            // 忽略版本检测错误
        }
    }

    /**
     * 检查数据库是否为空
     */
    public function isEmpty(): bool
    {
        if ($this->pdo === null) {
            return false;
        }

        try {
            $tables = $this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

            return count($tables) === 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * 获取数据库中的表列表
     */
    public function getTables(): array
    {
        if ($this->pdo === null) {
            return [];
        }

        try {
            return $this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * 检查是否已安装（通过检查管理员表）
     */
    public function isInstalled(): bool
    {
        if ($this->pdo === null) {
            return false;
        }

        try {
            // 检查 admins 表
            $adminsTable = $this->pdo->query("SHOW TABLES LIKE 'admins'");
            if ($adminsTable && $adminsTable->rowCount() > 0) {
                /** @noinspection SqlResolve */
                $adminCount = $this->pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
                if ($adminCount > 0) {
                    return true;
                }
            }

            // 检查 users 表
            $usersTable = $this->pdo->query("SHOW TABLES LIKE 'users'");
            if ($usersTable && $usersTable->rowCount() > 0) {
                /** @noinspection SqlResolve */
                $userCount = $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
                if ($userCount > 0) {
                    return true;
                }
            }

            return false;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * 获取最后的错误信息
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * 获取 PDO 实例
     */
    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }
}
