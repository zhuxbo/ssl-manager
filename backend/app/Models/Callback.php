<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Callback extends BaseModel
{
    protected $fillable = [
        'user_id',
        'url',
        'token',
        'status',
    ];

    protected $casts = [
        'status' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }
}
