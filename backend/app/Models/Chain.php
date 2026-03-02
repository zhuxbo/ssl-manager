<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Chain extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'common_name',
        'intermediate_cert',
    ];
}
