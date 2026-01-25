<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeployToken extends BaseModel
{
    protected $fillable = [
        'user_id',
        'token',
        'allowed_ips',
        'rate_limit',
        'last_used_at',
        'last_used_ip',
        'status',
    ];

    protected $hidden = [
        'token',
    ];

    protected $casts = [
        'allowed_ips' => 'array',
        'last_used_at' => 'datetime',
        'status' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }

    /**
     * 检查IP是否允许访问
     */
    public function isIpAllowed(string $ip): bool
    {
        if (empty($this->allowed_ips)) {
            return true;
        }

        return in_array($ip, $this->allowed_ips);
    }

    /**
     * 获取有效的限流值
     */
    public function getEffectiveRateLimit(int $defaultLimit = 60): int
    {
        return $this->rate_limit ?: $defaultLimit;
    }

    /**
     * 存储加密后的 token
     */
    public function setTokenAttribute($value): void
    {
        $this->attributes['token'] = empty($value) ? null : hash('sha256', $value);
    }

    /**
     * 通过原始 token 查找记录
     */
    public static function findByToken(string $token): ?self
    {
        return self::where('token', hash('sha256', $token))->first();
    }

    /**
     * 删除指定的token
     */
    public static function deleteTokenByToken(string $token): void
    {
        self::where('token', hash('sha256', $token))->delete();
    }

    /**
     * 删除指定用户的token
     */
    public static function deleteTokenByUserId(int $userId): void
    {
        self::where('user_id', $userId)->delete();
    }
}
