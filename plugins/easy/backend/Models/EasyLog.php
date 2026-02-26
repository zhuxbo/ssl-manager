<?php

namespace Plugins\Easy\Models;

use App\Models\BaseModel;

class EasyLog extends BaseModel
{
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
