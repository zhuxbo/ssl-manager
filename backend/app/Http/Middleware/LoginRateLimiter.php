<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class LoginRateLimiter
{
    use ApiResponse;

    /**
     * 处理传入的请求
     *
     * @param  string  $guard  认证守卫名称 (admin|user)
     */
    public function handle(Request $request, Closure $next, string $guard): mixed
    {
        $key = $guard.':'.$this->getRequestIpAddress($request);
        $account = $request->input('account') ?? '';
        if (! empty($account)) {
            $key = $guard.':'.$account;
        }

        // 检查该账号是否被锁定
        if (Cache::has($key.'_locked')) {
            $this->error('您的账号已被锁定，请联系管理员或稍后再试');
        }

        // 假设 RateLimiter 的配置: 5次尝试，10分钟窗口
        $maxAttemptsPerWindow = 5;
        $decayMinutes = 10;
        $lockoutAttempts = 10; // 达到10次失败则锁定

        // 检查是否超过限制 (在执行实际登录前)
        if (RateLimiter::tooManyAttempts($key, $maxAttemptsPerWindow)) {
            $seconds = RateLimiter::availableIn($key);
            $this->error('尝试次数过多，请'.$seconds.'秒后再试');
        }

        $response = $next($request);

        // $response 是 Illuminate\\Http\\JsonResponse (因为控制器抛出 ApiResponseException)
        $responseData = $response->getData(true);

        $code = isset($responseData['code']) ? (int) $responseData['code'] : null;

        $loginSucceeded = false;
        // 判断登录是否成功：检查响应中的 'code' 字段
        if ($code === 1) {
            $loginSucceeded = true;
        }

        if ($loginSucceeded) {
            // 登录成功，重置 RateLimiter 计数器 和 Cache 锁定标记 (如果存在)
            RateLimiter::clear($key);
            Cache::forget($key.'_locked');
        } else {
            // 登录失败，仅当是由业务逻辑错误 (code === 0) 导致时，才增加尝试次数
            if ($code === 0) {
                RateLimiter::hit($key, $decayMinutes * 60); // 增加计数器

                $attempts = RateLimiter::attempts($key);
                // 如果失败次数累计达到10次，锁定账号
                if ($attempts >= $lockoutAttempts) {
                    Cache::put($key.'_locked', true, now()->addHours(24)); // 锁定24小时
                    // 注意：此时 $response 已经生成，如果想立即返回锁定信息，
                    // 需要抛出新的 ApiResponseException，或者修改 $response (不推荐)。
                    // 当前实现下，锁定信息将在下一次请求时由顶部的 Cache::has() 捕获。
                }
            }
            // 对于其他类型的失败 (例如验证错误，通常有不同的HTTP状态码或没有 'code' 字段)，
            // 不计入登录失败尝试。
        }

        return $response;
    }

    /**
     * 获取请求IP地址
     */
    protected function getRequestIpAddress(Request $request): string
    {
        return $request->ip() ?? '127.0.0.1';
    }
}
