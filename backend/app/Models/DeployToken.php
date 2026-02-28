<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class DeployToken extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'token',
        'token_hash',
        'allowed_ips',
        'rate_limit',
        'last_used_at',
        'last_used_ip',
        'status',
    ];

    protected $hidden = [
        'token_hash',
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
     * 存储加密后的 token，同时设置 token_hash
     */
    public function setTokenAttribute($value): void
    {
        if (empty($value)) {
            $this->attributes['token'] = null;
            $this->attributes['token_hash'] = null;
        } else {
            $this->attributes['token'] = Crypt::encryptString($value);
            $this->attributes['token_hash'] = hash('sha256', $value);
        }
    }

    /**
     * 解密 token
     */
    public function getTokenAttribute($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * 通过原始 token 查找记录
     */
    public static function findByToken(string $token): ?self
    {
        return self::where('token_hash', hash('sha256', $token))->first();
    }

    /**
     * 删除指定的token
     */
    public static function deleteTokenByToken(string $token): void
    {
        self::where('token_hash', hash('sha256', $token))->delete();
    }

    /**
     * 删除指定用户的token
     */
    public static function deleteTokenByUserId(int $userId): void
    {
        self::where('user_id', $userId)->delete();
    }
}
