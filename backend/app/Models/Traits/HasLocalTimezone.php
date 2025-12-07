<?php

namespace App\Models\Traits;

use Carbon\Carbon;
use DateTimeInterface;

/**
 * 处理Eloquent模型的时区问题
 *
 * 使用方法：将此特性添加到需要本地时区的模型类中
 */
trait HasLocalTimezone
{
    /**
     * 为数组/JSON序列化准备日期值
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        // 将日期转换为当前时区，并格式化为带时区的ISO8601字符串
        return Carbon::instance($date)
            ->setTimezone(config('app.timezone'))
            ->toIso8601String();
    }
}
