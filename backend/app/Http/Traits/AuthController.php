<?php

namespace App\Http\Traits;

use App\Models\Admin;
use App\Models\User;

trait AuthController
{
    /**
     * 获取登录凭证
     */
    protected function getCredentials(string $account, string $password): array
    {
        if (empty($account) || empty($password)) {
            $this->error('账号或密码不能为空');
        }

        $credentials = ['password' => $password];

        if (filter_var($account, FILTER_VALIDATE_EMAIL)) {
            $credentials['email'] = $account;
        } elseif (preg_match('/^1[3-9]\d{9}$/', $account)) {
            $credentials['mobile'] = $account;
        } elseif (strlen($account) >= 3 && strlen($account) <= 20) {
            $credentials['username'] = $account;
        } else {
            $this->error('账号格式错误');
        }

        return $credentials;
    }

    /**
     * 尝试登录并获取token
     */
    protected function attemptLogin(array $credentials): string
    {
        if (! $accessToken = $this->guard->attempt($credentials)) {
            $this->error('账号或密码错误');
        }

        /** @var Admin|User $user */
        $user = $this->guard->user();
        if ($user->status === 0) {
            $this->guard->logout();
            $this->error('账号已被禁用');
        }

        return $accessToken;
    }
}
