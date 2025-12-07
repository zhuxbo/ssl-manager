<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TimezoneMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 从请求头获取时区，如果没有则使用默认时区Asia/Shanghai
        $timezone = $request->header('X-Timezone', 'Asia/Shanghai');

        // 设置PHP的时区
        date_default_timezone_set($timezone);

        // 设置Laravel的时区
        config(['app.timezone' => $timezone]);

        // 设置Carbon的时区
        Carbon::setLocale(config('app.locale', 'zh_CN'));

        return $next($request);
    }
}
