<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class UserLevel extends BaseModel
{
    protected $fillable = [
        'code',
        'name',
        'custom',
        'cost_rate',
        'weight',
    ];

    protected $casts = [
        'custom' => 'integer',
        'cost_rate' => 'float',
        'weight' => 'integer',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'code', 'level_code');
    }

    public function productPrices(): HasMany
    {
        return $this->hasMany(ProductPrice::class, 'code', 'level_code');
    }
}
