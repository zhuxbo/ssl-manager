<?php

namespace App\Models;

class CaLog extends BaseModel
{
    protected $connection = 'log';

    const null UPDATED_AT = null;

    protected $fillable = [
        'url',
        'api',
        'params',
        'response',
        'status_code',
        'status',
        'duration',
    ];

    protected $casts = [
        'params' => 'json',
        'response' => 'json',
        'status_code' => 'integer',
        'status' => 'integer',
        'duration' => 'decimal:2',
    ];
}
