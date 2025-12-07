<?php

namespace App\Models;

class Task extends BaseModel
{
    protected $fillable = [
        'order_id',
        'action',
        'result',
        'attempts',
        'started_at',
        'last_execute_at',
        'source',
        'weight',
        'status',
    ];

    protected $casts = [
        'result' => 'json',
        'started_at' => 'datetime',
        'last_execute_at' => 'datetime',
        'weight' => 'integer',
    ];

    // 写入时设置 weight 等于 id
    public static function boot(): void
    {
        parent::boot();
        static::created(function ($model) {
            // 如果weight为0（使用了默认值），则更新为id
            if ($model->weight === 0) {
                $model->update(['weight' => $model->id]);
            }
        });
    }
}
