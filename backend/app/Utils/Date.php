<?php

namespace App\Utils;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Throwable;

class Date
{
    private const int YEAR = 31536000; // 一年对应的秒数

    private const int MONTH = 2592000; // 一个月对应的秒数

    private const int WEEK = 604800;   // 一周对应的秒数

    private const int DAY = 86400;     // 一天对应的秒数

    private const int HOUR = 3600;     // 一小时对应的秒数

    private const int MINUTE = 60;     // 一分钟对应的秒数

    /**
     * 计算两个时区间相差的时长，单位为秒
     *
     * [!!] PHP 支持的时区列表可参考 <http://php.net/timezones>
     *
     * @param  string  $remote  要查找偏移量的时区
     * @param  string|null  $local  基准时区，默认为 null 使用默认时区
     * @param  string|int|null  $now  UNIX 时间戳或日期字符串
     * @return int 两个时区的偏移秒数
     *
     * @throws Throwable
     *
     * @example $seconds = Date::offset('America/Chicago', 'GMT');
     */
    public static function offset(string $remote, ?string $local = null, string|int|null $now = null): int
    {
        $local = $local ?? date_default_timezone_get();

        if (is_int($now)) {
            $now = date(DateTimeInterface::RFC2822, $now);
        }

        $zoneRemote = new DateTimeZone($remote);
        $zoneLocal = new DateTimeZone($local);

        $timeRemote = new DateTime($now, $zoneRemote);
        $timeLocal = new DateTime($now, $zoneLocal);

        return $zoneRemote->getOffset($timeRemote) - $zoneLocal->getOffset($timeLocal);
    }

    /**
     * 计算两个时间戳之间相差的时间
     *
     * 示例：
     * $span = Date::span(60, 182, 'minutes,seconds'); // array('minutes' => 2, 'seconds' => 2)
     * $span = Date::span(60, 182, 'minutes'); // 2
     *
     * @param  int  $remote  目标时间戳
     * @param  int|null  $local  基准时间戳，默认为 null 使用当前时间
     * @param  string  $output  格式化输出字符串，使用逗号分隔的时间单位
     * @return bool|array|int|string 返回关联数组、多种类型之一
     */
    public static function span(int $remote, ?int $local = null, string $output = 'years,months,weeks,days,hours,minutes,seconds'): bool|array|int|string
    {
        // 标准化输出格式
        $output = trim(strtolower($output));
        if (! $output) {
            // 输出格式无效
            return false;
        }

        // 分割输出格式为数组
        $units = preg_split('/[^a-z]+/', $output);
        if ($units === false) {
            return false;
        }

        // 初始化输出数组
        $result = array_combine($units, array_fill(0, count($units), 0));

        if ($local === null) {
            $local = time();
        }

        // 计算时间差的绝对值
        $timespan = abs($remote - $local);

        // 按顺序计算各时间单位的数量
        foreach ($result as $unit => &$count) {
            switch ($unit) {
                case 'years':
                    $count = intdiv($timespan, self::YEAR);
                    $timespan %= self::YEAR;
                    break;
                case 'months':
                    $count = intdiv($timespan, self::MONTH);
                    $timespan %= self::MONTH;
                    break;
                case 'weeks':
                    $count = intdiv($timespan, self::WEEK);
                    $timespan %= self::WEEK;
                    break;
                case 'days':
                    $count = intdiv($timespan, self::DAY);
                    $timespan %= self::DAY;
                    break;
                case 'hours':
                    $count = intdiv($timespan, self::HOUR);
                    $timespan %= self::HOUR;
                    break;
                case 'minutes':
                    $count = intdiv($timespan, self::MINUTE);
                    $timespan %= self::MINUTE;
                    break;
                case 'seconds':
                    $count = $timespan;
                    break;
                default:
                    // 未支持的单位，设置为0
                    $count = 0;
            }
        }
        unset($count); // 解除引用

        if (count($result) === 1) {
            // 仅请求单个时间单位，返回对应的数值
            return array_shift($result);
        }

        // 返回关联数组
        return $result;
    }

    /**
     * 格式化 UNIX 时间戳为人类可读的字符串
     *
     * @param  int  $remote  目标 Unix 时间戳
     * @param  int|null  $local  本地时间戳，默认为 null 使用当前时间
     * @return string 格式化后的日期字符串
     */
    public static function human(int $remote, ?int $local = null): string
    {
        $timeDiff = ($local ?? time()) - $remote;
        if ($timeDiff < 0) {
            return __('未来的时间');
        }

        $chunks = [
            [self::YEAR, '年'],
            [self::MONTH, '月'],
            [self::WEEK, '周'],
            [self::DAY, '天'],
            [self::HOUR, '小时'],
            [self::MINUTE, '分钟'],
            [1, '秒'],
        ];

        foreach ($chunks as [$seconds, $name]) {
            if (($count = intdiv($timeDiff, $seconds)) > 0) {
                return __("%d $name 前", [$count]);
            }
        }

        return __('刚刚');
    }

    /**
     * 获取一个基于时间偏移的 Unix 时间戳
     *
     * @param  string  $type  时间类型，默认为 day，可选值：minute, hour, day, week, month, quarter, year
     * @param  int  $offset  时间偏移量，默认为0，正数表示之后，负数表示之前
     * @param  string  $position  时间的位置，默认为 begin，可选值：begin, start, first, front, end
     * @param  int|null  $year  基准年，默认为 null 使用当前年
     * @param  int|null  $month  基准月，默认为 null 使用当前月
     * @param  int|null  $day  基准天，默认为 null 使用当前天
     * @param  int|null  $hour  基准小时，默认为 null 使用当前小时
     * @param  int|null  $minute  基准分钟，默认为 null 使用当前分钟
     * @return int 处理后的 Unix 时间戳
     */
    public static function unixTime(
        string $type = 'day',
        int $offset = 0,
        string $position = 'begin',
        ?int $year = null,
        ?int $month = null,
        ?int $day = null,
        ?int $hour = null,
        ?int $minute = null
    ): int {
        $year = $year ?? (int) date('Y');
        $month = $month ?? (int) date('m');
        $day = $day ?? (int) date('d');
        $hour = $hour ?? (int) date('H');
        $minute = $minute ?? (int) date('i');

        $isBegin = in_array(strtolower($position), ['begin', 'start', 'first', 'front'], true);

        return match (strtolower($type)) {
            'minute' => $isBegin
                ? mktime($hour, $minute + $offset, 0, $month, $day, $year)
                : mktime($hour, $minute + $offset, 59, $month, $day, $year),
            'hour' => $isBegin
                ? mktime($hour + $offset, 0, 0, $month, $day, $year)
                : mktime($hour + $offset, 59, 59, $month, $day, $year),
            'day' => $isBegin
                ? mktime(0, 0, 0, $month, $day + $offset, $year)
                : mktime(23, 59, 59, $month, $day + $offset, $year),
            'week' => $isBegin
                ? mktime(0, 0, 0, $month, $day - date('w', mktime(0, 0, 0, $month, $day, $year)) + 1 + 7 * $offset, $year)
                : mktime(23, 59, 59, $month, $day - date('w', mktime(0, 0, 0, $month, $day, $year)) + 7 + 7 * $offset, $year),
            'month' => $isBegin
                ? mktime(0, 0, 0, $month + $offset, 1, $year)
                : mktime(23, 59, 59, $month + $offset, cal_days_in_month(CAL_GREGORIAN, $month + $offset, $year), $year),
            'quarter' => $isBegin
                ? mktime(0, 0, 0, 1 + ((ceil($month / 3) + $offset - 1) * 3), 1, $year)
                : mktime(23, 59, 59, (ceil($month / 3) + $offset) * 3, cal_days_in_month(CAL_GREGORIAN, (ceil($month / 3) + $offset) * 3, $year), $year),
            'year' => $isBegin
                ? mktime(0, 0, 0, 1, 1, $year + $offset)
                : mktime(23, 59, 59, 12, 31, $year + $offset),
            default => mktime($hour, $minute, 0, $month, $day, $year),
        };
    }
}
