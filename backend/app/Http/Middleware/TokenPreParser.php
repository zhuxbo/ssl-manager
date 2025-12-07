<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use App\Traits\ExtractsToken;
use Closure;
use Illuminate\Http\Request;

class TokenPreParser
{
    use ExtractsToken;

    /**
     * Token 预解析中间件
     * 在限流之前执行，为限流提供 token 信息
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $this->extractToken($request);

        if ($token) {
            // 查找 API token（不验证状态和权限，只获取基本信息）
            $apiToken = ApiToken::where('token', hash('sha256', $token))->first();

            if ($apiToken) {
                // 只存储必要的 token 信息，不存储原始 token
                $request->attributes->set('api_token_info', $apiToken);
                // 存储 token 的哈希值用于标识，而不是原始值
                $request->attributes->set('token_hash', hash('sha256', $token));
            }
        }

        return $next($request);
    }
}
