<?php

namespace App\Http\Middleware;

use App\Services\LogBuffer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Terminable Middleware - 请求结束后刷新日志缓冲
 */
class FlushLogs
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * 请求结束后执行
     */
    public function terminate(Request $request, Response $response): void
    {
        LogBuffer::flush();
    }
}
