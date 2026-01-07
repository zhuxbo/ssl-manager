<?php

namespace App\Models\Acme;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcmeAuthorization extends BaseModel
{
    protected $table = 'acme_authorizations';

    protected $fillable = [
        'acme_order_id',
        'token',
        'identifier_type',
        'identifier_value',
        'wildcard',
        'status',
        'expires',
        'challenge_type',
        'challenge_token',
        'challenge_status',
        'challenge_validated',
    ];

    protected function casts(): array
    {
        return [
            'wildcard' => 'boolean',
            'expires' => 'datetime',
            'challenge_validated' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(AcmeOrder::class, 'acme_order_id');
    }
}
