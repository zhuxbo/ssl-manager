<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\UserRefreshToken;
use App\Traits\ApiResponse;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\TokenGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserRefreshTokenAuthenticate
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
        $guard = Auth::guard('user-refresh-token');

        if (! $guard->check()) {
            throw new AuthenticationException('Unauthorized');
        }

        /** @var UserRefreshToken $userRefreshToken */
        $userRefreshToken = $guard->user();

        if ($userRefreshToken->expires_at->isPast()) {
            UserRefreshToken::deleteTokenByToken($userRefreshToken->refresh_token);
            throw new AuthenticationException('Refresh token is expired');
        }

        $user = User::find($userRefreshToken->user_id, ['id', 'status']);

        if ($user?->status === 0) {
            UserRefreshToken::deleteTokenByUserId($user->id);
            throw new AuthenticationException('Account is disabled');
        }

        return $next($request);
    }
}
