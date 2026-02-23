<?php

namespace App\Models\Acme;

use App\Models\BaseModel;
use App\Models\Cert;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcmeAuthorization extends BaseModel
{
    protected $table = 'acme_authorizations';

    protected $fillable = [
        'cert_id',
        'token',
        'identifier_type',
        'identifier_value',
        'wildcard',
        'status',
        'expires',
        'challenge_type',
        'challenge_token',
        'acme_challenge_id',
        'key_authorization',
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

    public function cert(): BelongsTo
    {
        return $this->belongsTo(Cert::class);
    }
}
