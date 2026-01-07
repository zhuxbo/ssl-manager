<?php

namespace App\Models\Acme;

use Illuminate\Database\Eloquent\Model;

class AcmeNonce extends Model
{
    protected $table = 'acme_nonces';

    protected $primaryKey = 'nonce';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'nonce',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }
}
