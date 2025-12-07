<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class FilterUserIdParameter
{
    /**
     * 处理请求，移除 user_id 参数
     */
    public function handle(Request $request, Closure $next)
    {
        // 如果请求参数中有 user_id，移除它
        if ($request->has('user_id')) {
            $request->request->remove('user_id');
        }

        // 如果 GET 参数中有 user_id，移除它
        if ($request->query->has('user_id')) {
            $request->query->remove('user_id');
        }

        // 如果路由参数中有 user_id 并且不是路由中定义的必要参数，移除它
        $route = $request->route();
        if ($route) {
            $parameters = $route->parameters();
            $compiledRoute = $route->getCompiled();

            // 检查 user_id 是否为路由必需参数
            if (isset($parameters['user_id']) && ! $compiledRoute->getPathVariables('user_id')) {
                $route->forgetParameter('user_id');
            }
        }

        return $next($request);
    }
}
