<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Models\AdminRefreshToken;
use App\Traits\ApiResponse;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\TokenGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminRefreshTokenAuthenticate
{
    use ApiResponse;

    /**
     * API 认证中间件
     *
     * @throws AuthenticationException
     */
    public function handle(Request $request, Closure $next)
    {
        /** @var TokenGuard $guard */
        $guard = Auth::guard('admin-refresh-token');

        if (! $guard->check()) {
            throw new AuthenticationException('Unauthorized');
        }

        /** @var AdminRefreshToken $adminRefreshToken */
        $adminRefreshToken = $guard->user();

        if ($adminRefreshToken->expires_at->isPast()) {
            AdminRefreshToken::deleteTokenByToken($adminRefreshToken->refresh_token);
            throw new AuthenticationException('Refresh token is expired');
        }

        $admin = Admin::find($adminRefreshToken->admin_id, ['id', 'status']);

        if ($admin?->status === 0) {
            AdminRefreshToken::deleteTokenByAdminId($admin->id);
            throw new AuthenticationException('Account is disabled');
        }

        return $next($request);
    }
}
