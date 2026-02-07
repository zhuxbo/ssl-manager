<?php

namespace App\Models\Traits;

use App\Utils\SnowFlake;

trait HasSnowflakeId
{
    public static function bootHasSnowflakeId(): void
    {
        static::creating(function ($model) {
            if (! $model->id) {
                $model->id = SnowFlake::generateParticle();
            }
        });
    }

    public function getIncrementing(): false
    {
        return false;
    }
}
