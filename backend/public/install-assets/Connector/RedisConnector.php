<?php

namespace Install\Connector;

use Install\DTO\InstallConfig;
use Redis;
use Exception;

/**
 * Redis 连接器
 */
class RedisConnector
{
    private ?string $error = null;

    /**
     * 测试 Redis 连接
     */
    public function test(InstallConfig $config): bool
    {
        $this->error = null;

        // 检查 Redis 扩展
        if (! extension_loaded('redis')) {
            $this->error = 'Redis 扩展未加载，请先安装并启用 Redis 扩展';

            return false;
        }

        try {
            $redis = new Redis();

            // 尝试连接
            if (! $redis->connect($config->redisHost, $config->redisPort, 2)) {
                $this->error = '无法连接到 Redis 服务器，请检查 Redis 主机和端口配置';

                return false;
            }

            // 如果配置了密码，进行认证
            if (! empty($config->redisPassword)) {
                $result = $this->authenticate($redis, $config);
                if (! $result) {
                    return false;
                }
            } else {
                // 没有配置密码，直接尝试 ping
                $result = $this->tryPing($redis);
                if (! $result) {
                    return false;
                }
            }

            $redis->close();

            return true;
        } catch (Exception $e) {
            $this->error = 'Redis 连接测试失败: ' . $e->getMessage();

            return false;
        }
    }

    /**
     * Redis 认证
     */
    private function authenticate(Redis $redis, InstallConfig $config): bool
    {
        try {
            // Redis 6.0+ 支持用户名和密码
            if (! empty($config->redisUsername)) {
                $authResult = $redis->auth([$config->redisUsername, $config->redisPassword]);
            } else {
                // 旧版 Redis 只使用密码
                $authResult = $redis->auth($config->redisPassword);
            }

            if (! $authResult) {
                $this->error = 'Redis 认证失败，用户名或密码错误';

                return false;
            }

            // 认证成功后测试 ping
            return $this->tryPing($redis);
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();

            // 检查是否是认证相关的错误
            if (stripos($errorMsg, 'WRONGPASS') !== false
                || stripos($errorMsg, 'invalid password') !== false
                || stripos($errorMsg, 'invalid username-password') !== false
                || stripos($errorMsg, 'ERR invalid password') !== false) {
                $this->error = 'Redis 认证失败，用户名或密码错误';
            } else {
                $this->error = 'Redis 认证过程出错: ' . $errorMsg;
            }

            return false;
        }
    }

    /**
     * 尝试 ping 测试
     */
    private function tryPing(Redis $redis): bool
    {
        try {
            $pingResult = $redis->ping();

            if ($pingResult === true || $pingResult === '+PONG' || $pingResult === 'PONG') {
                return true;
            }

            $this->error = 'Redis PING 测试返回异常结果';

            return false;
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();

            // ping 失败，可能需要认证
            if (stripos($errorMsg, 'NOAUTH') !== false
                || stripos($errorMsg, 'Authentication required') !== false) {
                $this->error = 'Redis 服务器需要密码认证，请填写 Redis 密码';
            } else {
                $this->error = 'Redis PING 测试失败: ' . $errorMsg;
            }

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
}
