<?php

namespace App\Models;

use App\Bootstrap\ApiExceptions;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use JsonException;

class Setting extends BaseModel
{
    /**
     * 缓存前缀
     */
    private const string CACHE_PREFIX = 'setting:';

    /**
     * 缓存时间（秒），默认1小时
     */
    private const int CACHE_TTL = 3600;

    protected $fillable = [
        'group_id',
        'key',
        'type',
        'options',
        'is_multiple',
        'value',
        'description',
        'weight',
    ];

    protected $casts = [
        'options' => 'array',
        'is_multiple' => 'boolean',
    ];

    /**
     * 模型的"启动"方法
     */
    protected static function boot(): void
    {
        parent::boot();

        // 如果type不等于select 则is_multiple为0 options为空
        static::creating(function ($model) {
            if ($model->type !== 'select') {
                $model->is_multiple = 0;
                $model->options = null;
            }
        });

        static::created(function ($model) {
            $model->weight = $model->weight ?: $model->id;
            $model->save();
        });

        // 如果type不等于select 则is_multiple为0 options为空
        static::updating(function ($model) {
            if ($model->type !== 'select') {
                $model->is_multiple = 0;
                $model->options = null;
            }
        });

        // 数据变更时清除相关缓存
        static::saved(function ($model) {
            self::clearGroupCache($model->group_id);
        });

        static::deleted(function ($model) {
            self::clearGroupCache($model->group_id);
        });
    }

    /**
     * 在获取value属性时自动转换类型
     */
    public function getValueAttribute($value)
    {
        if (! empty($this->attributes['type']) && isset($this->attributes['value']) && $this->attributes['value'] === $value) {
            return $this->getTypedValue();
        }

        return $value;
    }

    /**
     * 在设置value属性时自动格式化
     */
    public function setValueAttribute($value): void
    {
        if (isset($this->attributes['type'])) {
            $this->attributes['value'] = $this->formatValue($value);
        } else {
            $this->attributes['value'] = $value;
        }
    }

    /**
     * 按组获取设置
     */
    public static function getByGroupId(int $groupId): array
    {
        $cacheKey = self::CACHE_PREFIX.'group:'.$groupId;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($groupId) {
            /** @var Setting[] $settings */
            $settings = self::where('group_id', $groupId)->orderBy('weight')->get();
            $result = [];

            foreach ($settings as $setting) {
                $result[$setting->key] = $setting->getTypedValue();
            }

            return $result;
        });
    }

    /**
     * 按组获取设置（使用组名）
     */
    public static function getByGroupName(string $groupName): array
    {
        $cacheKey = self::CACHE_PREFIX.'group_name:'.$groupName;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($groupName) {
            $group = SettingGroup::where('name', $groupName)->first();
            if (! $group) {
                return [];
            }

            return self::getByGroupId($group->id);
        });
    }

    /**
     * 按组设置多个值
     */
    public static function setByGroupId(int $groupId, array $values): bool
    {
        foreach ($values as $key => $value) {
            $setting = self::where('group_id', $groupId)->where('key', $key)->first();
            if ($setting) {
                $setting->value = $value;
                $setting->save();
            }
        }

        return true;
    }

    /**
     * 获取设置值
     */
    public static function getValue(string $groupName, ?string $key = null): mixed
    {
        if ($key === null) {
            return self::getByGroupName($groupName);
        }

        $cacheKey = self::CACHE_PREFIX.'value:'.$groupName.':'.$key;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($groupName, $key) {
            $group = SettingGroup::where('name', $groupName)->first();
            if (! $group) {
                return null;
            }

            $setting = self::where('group_id', $group->id)
                ->where('key', $key)
                ->first();

            return $setting?->value;
        });
    }

    /**
     * 设置值
     */
    public static function setValue(string $groupName, int|string|array $keyOrValues, mixed $value = null): bool
    {
        $group = SettingGroup::where('name', $groupName)->first();
        if (! $group) {
            return false;
        }

        if (is_array($keyOrValues)) {
            return self::setByGroupId($group->id, $keyOrValues);
        }

        $setting = self::where('group_id', $group->id)
            ->where('key', $keyOrValues)
            ->first();

        if ($setting) {
            $setting->value = $value;

            return $setting->save();
        }

        return false;
    }

    /**
     * 获取正确类型的值
     */
    public function getTypedValue(): mixed
    {
        if (! isset($this->attributes['type']) || ! isset($this->attributes['value'])) {
            return null;
        }

        $value = $this->attributes['value'];
        $type = $this->attributes['type'];
        $isMultiple = $this->attributes['is_multiple'] ?? false;

        try {
            return match ($type) {
                'integer' => (int) $value,
                'float' => (float) $value,
                'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                'array' => json_decode($value, true, 512, JSON_THROW_ON_ERROR),
                'select' => $isMultiple ? json_decode($value, true, 512, JSON_THROW_ON_ERROR) : $value,
                'base64' => base64_decode($value),
                default => $value,
            };
        } catch (JsonException $e) {
            app(ApiExceptions::class)->logException($e);

            return match ($type) {
                'array', 'select' => $isMultiple ? [] : null,
                default => null,
            };
        }
    }

    /**
     * 格式化要保存的值
     */
    protected function formatValue(mixed $value): string
    {
        if (! isset($this->attributes['type'])) {
            return (string) $value;
        }
        $type = $this->attributes['type'];
        $isMultiple = $this->attributes['is_multiple'] ?? false;

        try {
            return match ($type) {
                'array' => json_encode($value ?? [], JSON_THROW_ON_ERROR),
                'select' => $isMultiple ? json_encode($value, JSON_THROW_ON_ERROR) : (string) $value,
                'boolean' => $value ? '1' : '0',
                'base64' => base64_encode((string) $value),
                default => (string) $value,
            };
        } catch (JsonException $e) {
            app(ApiExceptions::class)->logException($e);

            return match ($type) {
                'array', 'select' => '[]',
                default => '',
            };
        }
    }

    /**
     * 设置项所属的设置组
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(SettingGroup::class, 'group_id');
    }

    /**
     * 清除指定组的缓存
     */
    public static function clearGroupCache(int $groupId): void
    {
        // 清除按组ID查询的缓存
        Cache::forget(self::CACHE_PREFIX.'group:'.$groupId);

        // 清除按组名查询的缓存（需要查询组名）
        $group = SettingGroup::find($groupId);
        if ($group) {
            Cache::forget(self::CACHE_PREFIX.'group_name:'.$group->name);

            // 清除该组下所有配置项的单个值缓存
            $settings = self::where('group_id', $groupId)->get(['key']);
            foreach ($settings as $setting) {
                Cache::forget(self::CACHE_PREFIX.'value:'.$group->name.':'.$setting->key);
            }
        }
    }

    /**
     * 清除所有 Setting 缓存
     */
    public static function clearAllCache(): void
    {
        // 获取所有缓存键并删除
        $groups = SettingGroup::all();
        foreach ($groups as $group) {
            self::clearGroupCache($group->id);
        }

        // 或者使用通配符删除（如果缓存驱动支持）
        // Redis 支持，但 file/database 驱动不支持
        // Cache::flush(); // 这会清除所有缓存，不推荐
    }
}
