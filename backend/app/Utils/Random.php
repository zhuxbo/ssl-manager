<?php

/** @noinspection PhpDocMissingThrowsInspection */

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace App\Utils;

/**
 * Class Random
 *
 * 用于生成安全的随机数、UUID 和字符串
 * 注意：此类假定运行在 PHP 7.0+ 环境中 (推荐 PHP 8.0+)
 */
class Random
{
    /**
     * 获取全球唯一标识 (UUID v4)
     */
    public static function uuid(): string
    {
        // 此实现依赖 random_int()，在 PHP 7.0+ 中确定存在
        // 它会安全地抛出异常，如果系统无法提供安全的随机源
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xFFFF), random_int(0, 0xFFFF), // 32 bits for "time_low"
            random_int(0, 0xFFFF), // 16 bits for "time_mid"
            random_int(0, 0x0FFF) | 0x4000, // 16 bits for "time_hi_and_version" (version 4)
            random_int(0, 0x3FFF) | 0x8000, // 16 bits for "clk_seq_hi_res" and "clk_seq_low" (variant 1)
            random_int(0, 0xFFFF), random_int(0, 0xFFFF), random_int(0, 0xFFFF) // 48 bits for "node"
        );
    }

    /**
     * 随机字符生成
     *
     * @param  string  $type  类型:
     *                        - 'alpha': 仅字母
     *                        - 'alnum': 字母和数字
     *                        - 'numeric': 仅数字
     *                        - 'noZero': 不含0的数字
     *                        - 'token': [推荐] 32位十六进制安全令牌 (类似无横线UUID)
     *                        - 'md5': 一个基于随机字节的 32位 MD5 哈希值
     *                        - 'sha256': 一个基于随机字节的 64位 SHA256 哈希值
     * @param  int  $length  长度 (仅对 'alpha', 'alnum', 'numeric', 'noZero' 有效)
     */
    public static function build(string $type = 'alnum', int $length = 8): string
    {
        $types = ['alpha', 'alnum', 'numeric', 'noZero', 'token', 'md5', 'sha256'];
        if (! in_array($type, $types, true)) {
            $type = 'alnum';
        }

        if ($length < 8) {
            $length = 8;
        } elseif ($length > 512) {
            $length = 512;
        }

        return match ($type) {
            'alpha', 'alnum', 'numeric', 'noZero' => self::generateFromPool($type, $length),

            // 生成一个 32 字符的安全十六进制令牌 (16 字节 -> 32 字符)
            // 这是生成 32位 令牌的最标准、最安全的方法。
            'token' => bin2hex(random_bytes(16)),

            // 生成一个 32 字符的 MD5 十六进制哈希值
            // 我们使用 32 字节的安全随机数据作为输入
            'md5' => md5(random_bytes(32)),

            // 生成一个 64 字符的 SHA256 十六进制哈希值
            // 我们使用 32 字节的安全随机数据作为输入
            'sha256' => hash('sha256', random_bytes(32)),
        };
    }

    /**
     * 根据类型和长度生成随机字符串
     */
    private static function generateFromPool(string $type, int $length): string
    {
        if (! in_array($type, ['alpha', 'alnum', 'numeric', 'noZero'], true)) {
            $type = 'alnum';
        }

        $pool = match ($type) {
            'alpha' => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'alnum' => '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'numeric' => '0123456789',
            'noZero' => '123456789',
        };

        $str = '';
        $poolLength = strlen($pool) - 1;
        for ($i = 0; $i < $length; $i++) {
            // 使用 random_int 来安全地从池中选择字符
            $str .= $pool[random_int(0, $poolLength)];
        }

        return $str;
    }
}
