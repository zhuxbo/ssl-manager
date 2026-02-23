<?php

namespace App\Models\Acme;

use App\Models\BaseModel;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcmeAccount extends BaseModel
{
    protected $table = 'acme_accounts';

    protected $fillable = [
        'user_id',
        'order_id',
        'acme_account_id',
        'key_id',
        'public_key',
        'contact',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'public_key' => 'array',
            'contact' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
