<?php

namespace App\Models\Acme;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcmeAccount extends BaseModel
{
    protected $table = 'acme_accounts';

    protected $fillable = [
        'user_id',
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

    public function orders(): HasMany
    {
        return $this->hasMany(AcmeOrder::class, 'acme_account_id');
    }
}
