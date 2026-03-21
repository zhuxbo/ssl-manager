<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderDocument extends BaseModel
{
    protected $fillable = [
        'order_id',
        'user_id',
        'type',
        'file_name',
        'file_path',
        'file_size',
        'description',
        'uploaded_by',
        'submitted',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'submitted' => 'boolean',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
