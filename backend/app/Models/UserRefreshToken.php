<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UserRefreshToken extends BaseModel
{
    const null UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'refresh_token',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * 关联用户模型
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }

    /**
     * 创建刷新令牌
     */
    public static function createToken(int $userId): string
    {
        // 生成原始token
        $plainToken = Str::random(64);

        // 创建新令牌记录
        self::create([
            'user_id' => $userId,
            'refresh_token' => $plainToken,
            'expires_at' => now()->addMinutes(
                config('auth.refresh_token_ttl.user')
            ),
        ]);

        return $plainToken;
    }

    /**
     * 存储加密后的 refresh token
     */
    public function setRefreshTokenAttribute($value): void
    {
        $this->attributes['refresh_token'] = hash('sha256', $value);
    }

    /**
     * 删除指定的刷新令牌
     */
    public static function deleteTokenByToken(string $token): void
    {
        self::where('refresh_token', hash('sha256', $token))->delete();
    }

    /**
     * 删除指定用户的刷新令牌
     */
    public static function deleteTokenByUserId(int $userId): void
    {
        self::where('user_id', $userId)->delete();
    }
}
