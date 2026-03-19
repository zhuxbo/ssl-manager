<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderVerificationReport extends BaseModel
{
    protected $fillable = [
        'order_id',
        'user_id',
        'report_data',
        'submitted',
    ];

    protected $casts = [
        'report_data' => 'json',
        'submitted' => 'boolean',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
