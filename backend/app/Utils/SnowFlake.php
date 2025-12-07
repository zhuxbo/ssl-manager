<?php

declare(strict_types=1);

namespace App\Utils;

use Illuminate\Support\Facades\Redis as RedisFacade;
use InvalidArgumentException;
use Redis;
use RuntimeException;

class SnowFlake
{
    /**
     * 起始时间戳（毫秒）
     */
    const int EPOCH = 1696152000000;

    /**
     * 41位时间偏移基数
     */
    const int MAX_41_BIT = 1099511627775;

    /**
     * @var int 机器ID，需外部设置，每台机器唯一[0-7]
     */
    protected static int $machineId = 0;

    /**
     * @var string 锁Key
     */
    protected static string $lockKey = 'snowflake_lock';

    /**
     * @var string 存储 last 和 count 的哈希key
     */
    protected static string $stateKey = 'snowflake_state';

    /**
     * @var Redis|null 原生Redis实例
     */
    protected static ?Redis $redisClient = null;

    /**
     * 初始化Redis连接，通过Laravel Facade获取原生Redis对象
     */
    protected static function initRedis(): void
    {
        if (self::$redisClient === null) {
            // 通过Laravel的Redis门面获取PhpRedis连接对象
            // 然后获取底层原生 \Redis 客户端实例
            $connection = RedisFacade::connection();
            self::$redisClient = $connection->client();
        }
    }

    /**
     * 设置机器ID
     */
    public static function setMachineId(int $mId): void
    {
        if ($mId < 0 || $mId > 7) {
            throw new InvalidArgumentException('机器ID必须在0到7之间');
        }
        self::$machineId = $mId;
    }

    /**
     * 尝试获取Redis锁
     */
    protected static function acquireLock(int $timeoutSeconds = 1, int $retryCount = 10, int $retryDelayUs = 50000): bool
    {
        self::initRedis();
        for ($i = 0; $i < $retryCount; $i++) {
            // 使用 NX EX 参数获取锁，Redis原生客户端的set参数需组合成数组或使用rawCommand
            // phpredis原生: $redis->set($key, $value, ['nx', 'ex' => $timeout])
            $res = self::$redisClient->set(self::$lockKey, '1', ['nx', 'ex' => $timeoutSeconds]);
            if ($res === true || $res === 'OK') {
                return true;
            }
            usleep($retryDelayUs); // 等待后重试
        }

        return false;
    }

    /**
     * 释放Redis锁
     */
    protected static function releaseLock(): void
    {
        self::$redisClient->del(self::$lockKey);
    }

    /**
     * 初始化 state（只执行一次，如果初次运行可先初始化）
     */
    public static function initState(): void
    {
        self::initRedis();
        if (! self::$redisClient->exists(self::$stateKey)) {
            self::$redisClient->hMset(self::$stateKey, [
                'last' => 0,
                'count' => 0,
            ]);
        }
    }

    /**
     * 生成雪花ID
     *
     * @throws RuntimeException
     */
    public static function generateParticle(): int
    {
        if (self::$machineId === 0) {
            self::setMachineId((int) config('app.snowflake.machine_id'));
        }

        self::initState();

        // 获取Redis锁
        if (! self::acquireLock()) {
            throw new RuntimeException('无法获取雪花锁，生成ID失败');
        }

        try {
            $currentTime = (int) floor(microtime(true) * 1000);
            $offsetTime = $currentTime - self::EPOCH;

            // 返回数组索引值
            $state = self::$redisClient->hMget(self::$stateKey, ['last', 'count']);
            $last = (int) $state['last'];
            $count = (int) $state['count'];

            // 检测回拨
            if ($offsetTime < $last) {
                throw new RuntimeException('检测到时钟回拨，拒绝生成雪花ID');
            }

            // 同一毫秒内count递增，如果溢出则等待下毫秒
            if ($offsetTime == $last) {
                $count = ($count + 1) & 7;
                if ($count == 0) {
                    // count用尽等待下一个毫秒
                    while (true) {
                        usleep(1000); // 等待1ms
                        $currentTime = (int) floor(microtime(true) * 1000);
                        $offsetTime = $currentTime - self::EPOCH;
                        if ($offsetTime > $last) {
                            $count = 0;
                            break;
                        }
                    }
                }
            } else {
                $count = 0;
            }

            $base = decbin(self::MAX_41_BIT + $offsetTime);
            $machineIdBin = str_pad(decbin(self::$machineId), 3, '0', STR_PAD_LEFT);
            $sequence = str_pad(decbin($count), 3, '0', STR_PAD_LEFT);
            $binaryStr = $base.$machineIdBin.$sequence;
            $id = bindec($binaryStr);

            // 更新状态
            self::$redisClient->hMset(self::$stateKey, [
                'last' => $offsetTime,
                'count' => $count,
            ]);

            return $id;
        } finally {
            self::releaseLock();
        }
    }
}
