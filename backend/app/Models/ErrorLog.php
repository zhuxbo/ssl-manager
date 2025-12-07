<?php

namespace App\Models;

class ErrorLog extends BaseModel
{
    protected $connection = 'log';

    const null UPDATED_AT = null;

    protected $fillable = [
        'method',
        'url',
        'exception',
        'message',
        'trace',
        'status_code',
        'ip',
    ];

    protected $casts = [
        'trace' => 'json',
        'status_code' => 'integer',
    ];
}
