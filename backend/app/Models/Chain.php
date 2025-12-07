<?php

namespace App\Models;

class Chain extends BaseModel
{
    protected $fillable = [
        'common_name',
        'intermediate_cert',
    ];
}
