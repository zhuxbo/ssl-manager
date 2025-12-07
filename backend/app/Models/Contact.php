<?php

namespace App\Models;

use App\Models\Traits\HasSnowflakeId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contact extends BaseModel
{
    use HasSnowflakeId;

    protected $fillable = [
        'user_id',
        'last_name',
        'first_name',
        'identification_number',
        'title',
        'email',
        'phone',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }
}
