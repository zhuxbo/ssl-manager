<?php

namespace Tests\Traits;

use App\Models\Admin;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * 管理员 JWT 认证辅助
 *
 * 用于测试需要管理员身份认证的 API 接口
 */
trait ActsAsAdmin
{
    /**
     * 模拟管理员登录
     *
     * @param  Admin|null  $admin  指定管理员实例，为空时自动创建
     * @return $this
     */
    protected function actingAsAdmin(?Admin $admin = null): static
    {
        $admin ??= Admin::factory()->create();
        $token = JWTAuth::fromUser($admin);

        return $this->withHeaders([
            'Authorization' => "Bearer $token",
        ]);
    }
}
