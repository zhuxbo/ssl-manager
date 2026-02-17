<?php

namespace App\Services;

/**
 * 日志缓冲服务
 *
 * 将日志缓存在内存中，请求结束后批量写入数据库
 */
class LogBuffer
{
    /**
     * @var array<string, array<int, array<string, mixed>>>
     */
    private static array $logs = [];

    /**
     * 添加日志到缓冲区
     *
     * @param  class-string  $model  模型类名
     * @param  array<string, mixed>  $data  日志数据
     */
    public static function add(string $model, array $data): void
    {
        if (! isset(self::$logs[$model])) {
            self::$logs[$model] = [];
        }
        self::$logs[$model][] = $data;
    }

    /**
     * 刷新缓冲区，批量写入所有日志
     */
    public static function flush(): void
    {
        if (empty(self::$logs)) {
            return;
        }

        foreach (self::$logs as $model => $items) {
            if (empty($items)) {
                continue;
            }

            try {
                // 添加 created_at 时间戳
                $now = now()->toDateTimeString();
                $itemsWithTimestamp = array_map(function ($item) use ($now) {
                    $item['created_at'] = $now;

                    // 手动 JSON 序列化数组/对象值，因为 Model::insert() 走 Query Builder 不触发 Eloquent cast
                    foreach ($item as $key => $value) {
                        if (is_array($value) || is_object($value)) {
                            $item[$key] = json_encode($value, JSON_UNESCAPED_UNICODE);
                        }
                    }

                    return $item;
                }, $items);

                $model::insert($itemsWithTimestamp);
            } catch (\Throwable $e) {
                // 批量插入失败时，尝试逐条插入
                \Log::warning("LogBuffer: batch insert failed, fallback to single insert", [
                    'model' => $model,
                    'count' => count($items),
                    'error' => $e->getMessage(),
                ]);
                foreach ($items as $item) {
                    try {
                        $model::create($item);
                    } catch (\Throwable $createException) {
                        // 单条插入也失败时，记录到文件日志
                        \Log::error('LogBuffer: Failed to insert log', [
                            'model' => $model,
                            'data' => $item,
                            'error' => $createException->getMessage(),
                        ]);
                    }
                }
            }
        }

        self::$logs = [];
    }

    /**
     * 清空缓冲区（不写入）
     */
    public static function clear(): void
    {
        self::$logs = [];
    }

    /**
     * 获取缓冲区中的日志数量
     */
    public static function count(): int
    {
        $count = 0;
        foreach (self::$logs as $items) {
            $count += count($items);
        }

        return $count;
    }
}
