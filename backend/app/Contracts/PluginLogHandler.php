<?php

namespace App\Contracts;

interface PluginLogHandler
{
    /**
     * 判断是否应该处理该请求的日志
     */
    public function shouldHandle(string $path): bool;

    /**
     * 处理日志记录
     */
    public function handle(array $logData): void;
}
