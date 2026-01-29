<?php

declare(strict_types=1);

namespace App\Services\Acme;

use App\Models\Acme\AcmeNonce;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class NonceService
{
    private int $ttl;

    public function __construct()
    {
        $this->ttl = config('acme.nonce.ttl', 3600);
    }

    /**
     * 生成新的 Nonce
     */
    public function generate(): string
    {
        $nonce = Str::random(32).bin2hex(random_bytes(16));
        $expiresAt = now()->addSeconds($this->ttl);

        // 使用 Redis 缓存
        Cache::put("acme_nonce:$nonce", true, $expiresAt);

        return $nonce;
    }

    /**
     * 验证并消费 Nonce（原子操作）
     */
    public function verify(string $nonce): bool
    {
        $key = "acme_nonce:$nonce";

        // 使用 pull() 原子操作，避免竞态条件
        // pull() 会同时获取并删除缓存，返回 null 表示不存在
        return Cache::pull($key) !== null;
    }

    /**
     * 清理过期的 Nonce（用于数据库存储方式）
     */
    public function cleanup(): int
    {
        return AcmeNonce::where('expires_at', '<', now())->delete();
    }
}
