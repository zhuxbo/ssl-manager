<?php

namespace Tests\Traits;

use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * 用户 JWT 认证辅助
 *
 * 用于测试需要用户身份认证的 API 接口
 */
trait ActsAsUser
{
    /**
     * 模拟用户登录
     *
     * @param  User|null  $user  指定用户实例，为空时自动创建
     * @return $this
     */
    protected function actingAsUser(?User $user = null): static
    {
        $user ??= User::factory()->create();
        $token = JWTAuth::claims(['guard' => 'user'])->fromUser($user);

        return $this->withHeaders([
            'Authorization' => "Bearer $token",
        ]);
    }
}
