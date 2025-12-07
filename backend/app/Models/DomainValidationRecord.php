<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainValidationRecord extends BaseModel
{
    // Laravel 不更新时间戳
    public const null UPDATED_AT = null;

    protected $fillable = [
        'order_id',
        'last_check_at',
        'next_check_at',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'last_check_at' => 'datetime',
        'next_check_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
