<?php

namespace App\Auth;

use App\Traits\ExtractsToken;
use Illuminate\Auth\TokenGuard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;

class ExtendedTokenGuard extends TokenGuard
{
    use ExtractsToken;

    public function __construct(UserProvider $provider, Request $request, $inputKey = 'token', $storageKey = 'token', $hash = false)
    {
        parent::__construct($provider, $request, $inputKey, $storageKey, $hash);
    }

    /**
     * 获取当前请求的令牌
     * 重写父类方法以支持更多的token提取方式
     */
    public function getTokenForRequest(): ?string
    {
        // 使用我们的ExtractsToken trait来提取token
        $token = $this->extractToken($this->request);

        if ($token) {
            return $token;
        }

        // 如果没有找到token，回退到父类的默认行为
        return parent::getTokenForRequest();
    }
}
