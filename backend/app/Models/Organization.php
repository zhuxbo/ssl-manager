<?php

namespace App\Models;

use App\Models\Traits\HasSnowflakeId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Organization extends BaseModel
{
    use HasSnowflakeId;

    protected $fillable = [
        'user_id',
        'name',
        'registration_number',
        'country',
        'state',
        'city',
        'address',
        'postcode',
        'phone',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }
}
