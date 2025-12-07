<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class SettingGroup extends BaseModel
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'title',
        'description',
        'weight',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::created(function ($model) {
            $model->weight = $model->weight ?: $model->id;
            $model->save();
        });
    }

    /**
     * 获取设置组下的所有设置项
     */
    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class, 'group_id');
    }
}
