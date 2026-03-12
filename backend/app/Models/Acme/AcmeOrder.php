<?php

namespace App\Models\Acme;

use App\Models\BaseModel;
use App\Models\Product;
use App\Models\Traits\HasSnowflakeId;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcmeOrder extends BaseModel
{
    use HasFactory, HasSnowflakeId;

    protected $table = 'acme_orders';

    protected $fillable = [
        'user_id',
        'product_id',
        'latest_cert_id',
        'brand',
        'period',
        'amount',
        'purchased_standard_count',
        'purchased_wildcard_count',
        'eab_kid',
        'eab_hmac',
        'eab_used_at',
        'period_from',
        'period_till',
        'cancelled_at',
        'auto_renew',
        'auto_reissue',
        'admin_remark',
        'remark',
    ];

    protected $hidden = [
        'eab_hmac',
    ];

    protected function casts(): array
    {
        return [
            'period' => 'integer',
            'amount' => 'decimal:2',
            'purchased_standard_count' => 'integer',
            'purchased_wildcard_count' => 'integer',
            'eab_hmac' => 'encrypted',
            'eab_used_at' => 'datetime',
            'period_from' => 'datetime',
            'period_till' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getAutoRenewAttribute($value): ?bool
    {
        return $value === null ? null : (bool) $value;
    }

    public function getAutoReissueAttribute($value): ?bool
    {
        return $value === null ? null : (bool) $value;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function certs(): HasMany
    {
        return $this->hasMany(AcmeCert::class, 'order_id');
    }

    public function latestCert(): BelongsTo
    {
        return $this->belongsTo(AcmeCert::class, 'latest_cert_id');
    }
}
