<?php

namespace App\Models;

use App\Models\Traits\HasSnowflakeId;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends BaseModel implements AuthenticatableContract, JWTSubject
{
    use Authenticatable, HasSnowflakeId, MustVerifyEmail, Notifiable;

    protected $fillable = [
        'username',
        'email',
        'mobile',
        'balance',
        'level_code',
        'custom_level_code',
        'credit_limit',
        'invoice_limit',
        'last_login_at',
        'last_login_ip',
        'join_ip',
        'join_at',
        'source',
        'password',
        'token_version',
        'logout_at',
        'email_verified_at',
        'status',
        'notification_settings',
        'auto_settings',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'credit_limit' => 'decimal:2',
        'invoice_limit' => 'decimal:2',
        'last_login_at' => 'datetime',
        'join_at' => 'datetime',
        'logout_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'notification_settings' => 'json',
        'auto_settings' => 'json',
    ];

    /**
     * 获取 JWT 标识符
     */
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * 获取 JWT 自定义声明
     */
    public function getJWTCustomClaims(): array
    {
        return ['token_version' => $this->getAttribute('token_version') ?? 0];
    }

    /**
     * 设置密码
     */
    public function setPasswordAttribute(?string $value): void
    {
        if ($value) {
            $this->attributes['password'] = Hash::make($value);
        }
    }

    /**
     * 获取实体的通知
     */
    public function notifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'notifiable');
    }

    /**
     * 获取订单
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * 获取证书
     */
    public function certs(): HasManyThrough
    {
        return $this->hasManyThrough(Cert::class, Order::class);
    }

    /**
     * 获取用户等级
     */
    public function level(): BelongsTo
    {
        return $this->belongsTo(UserLevel::class, 'level_code', 'code');
    }

    /**
     * 获取自定义用户等级
     */
    public function customLevel(): BelongsTo
    {
        return $this->belongsTo(UserLevel::class, 'custom_level_code', 'code');
    }

    /**
     * 获取联系人
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /**
     * 获取组织
     */
    public function organizations(): HasMany
    {
        return $this->hasMany(Organization::class);
    }

    /**
     * 获取 API 令牌
     */
    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    /**
     * 获取回调设置
     */
    public function callbacks(): HasMany
    {
        return $this->hasMany(Callback::class);
    }

    /**
     * 获取 CNAME 委托
     */
    public function cnameDelegations(): HasMany
    {
        return $this->hasMany(CnameDelegation::class);
    }

    /**
     * 获取资金
     */
    public function funds(): HasMany
    {
        return $this->hasMany(Fund::class);
    }

    /**
     * 获取交易
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * 获取发票
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * 获取发票限额
     */
    public function invoiceLimits(): HasMany
    {
        return $this->hasMany(InvoiceLimit::class);
    }

    /**
     * 设置信用额度 始终为负值
     */
    protected function setCreditLimitAttribute(string $value): void
    {
        $this->attributes['credit_limit'] = abs((float) $value) * -1;
    }

    /**
     * 获取通知配置
     */
    public function getNotificationSettingsAttribute($value): array
    {
        return $this->normalizeNotificationSettings($value);
    }

    /**
     * 设置通知配置
     */
    public function setNotificationSettingsAttribute($value): void
    {
        $this->attributes['notification_settings'] = json_encode(
            $this->normalizeNotificationSettings($value)
        );
    }

    /**
     * 判断指定通道与类型是否允许发送通知
     */
    public function allowsNotificationChannel(string $channel, string $type): bool
    {
        $settings = $this->notification_settings ?? [];

        return (bool) data_get($settings, $channel.'.'.$type, true);
    }

    /**
     * 获取自动设置
     */
    public function getAutoSettingsAttribute($value): array
    {
        return $this->normalizeAutoSettings($value);
    }

    /**
     * 设置自动设置
     */
    public function setAutoSettingsAttribute($value): void
    {
        $this->attributes['auto_settings'] = json_encode(
            $this->normalizeAutoSettings($value)
        );
    }

    /**
     * 归一化自动设置
     */
    protected function normalizeAutoSettings(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true) ?? [];
        }

        if (! is_array($value)) {
            $value = [];
        }

        return [
            'auto_renew' => (bool) ($value['auto_renew'] ?? false),
            'auto_reissue' => (bool) ($value['auto_reissue'] ?? false),
        ];
    }

    /**
     * 归一化通知配置
     */
    protected function normalizeNotificationSettings(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true) ?? [];
        }

        if (! is_array($value)) {
            $value = [];
        }

        $defaults = config('notification.user_default_preferences', []);
        $normalized = [];

        foreach ($defaults as $channel => $types) {
            foreach ($types as $type => $default) {
                $normalized[$channel][$type] = (bool) data_get($value, $channel.'.'.$type, $default);
            }
        }

        return $normalized;
    }
}
