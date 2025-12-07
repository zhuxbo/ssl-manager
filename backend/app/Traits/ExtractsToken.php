<?php

namespace App\Traits;

use Illuminate\Http\Request;

trait ExtractsToken
{
    /**
     * 从请求中提取 token
     */
    private function extractToken(Request $request): ?string
    {
        // 从 Authorization header 提取
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        // 从 Api-Token header 提取
        $apiTokenHeader = $request->header('Api-Token');
        if ($apiTokenHeader) {
            return $apiTokenHeader;
        }

        // 从 query 参数提取
        $token = $request->query('api-token');
        if ($token) {
            return $token;
        }

        // 从 query 参数提取
        $token = $request->input('api-token');
        if ($token) {
            return $token;
        }

        // 从 query 参数提取
        $token = $request->query('token');
        if ($token) {
            return $token;
        }

        // 从 form 参数提取
        return $request->input('token');
    }
}
