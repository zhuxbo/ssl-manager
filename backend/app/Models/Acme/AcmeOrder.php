<?php

namespace App\Models\Acme;

use App\Models\BaseModel;
use App\Models\Order;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcmeOrder extends BaseModel
{
    protected $table = 'acme_orders';

    protected $fillable = [
        'acme_account_id',
        'order_id',
        'identifiers',
        'expires',
        'status',
        'finalize_token',
        'certificate_token',
        'csr',
        'certificate',
        'chain',
    ];

    protected function casts(): array
    {
        return [
            'identifiers' => 'array',
            'expires' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AcmeAccount::class, 'acme_account_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function authorizations(): HasMany
    {
        return $this->hasMany(AcmeAuthorization::class, 'acme_order_id');
    }
}
