<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AdminRefreshToken extends BaseModel
{
    const null UPDATED_AT = null;

    protected $fillable = [
        'admin_id',
        'refresh_token',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * 关联管理员模型
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class)->withoutGlobalScopes();
    }

    /**
     * 创建刷新令牌
     */
    public static function createToken(int $adminId): string
    {
        // 生成原始token
        $plainToken = Str::random(64);

        // 创建新令牌记录
        self::create([
            'admin_id' => $adminId,
            'refresh_token' => $plainToken,
            'expires_at' => now()->addMinutes(
                config('auth.refresh_token_ttl.admin')
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
     * 删除指定管理员的刷新令牌
     */
    public static function deleteTokenByAdminId(int $adminId): void
    {
        self::where('admin_id', $adminId)->delete();
    }
}
