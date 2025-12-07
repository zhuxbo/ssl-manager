<?php

namespace App\Models;

class EasyLog extends BaseModel
{
    protected $connection = 'log';

    const null UPDATED_AT = null;

    protected $fillable = [
        'method',
        'url',
        'params',
        'response',
        'ip',
        'status',
    ];

    protected $casts = [
        'params' => 'json',
        'response' => 'json',
        'status' => 'integer',
    ];
}
