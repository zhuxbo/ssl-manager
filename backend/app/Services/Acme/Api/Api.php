<?php

declare(strict_types=1);

namespace App\Services\Acme\Api;

use App\Traits\ApiResponse;

class Api
{
    use ApiResponse;

    /**
     * 按来源获取 ACME API 实现（与传统 Order\Api\Api 一致的模式）
     */
    public function getSourceApi(string $source): AcmeSourceApiInterface
    {
        ! $source && $this->error('ACME 来源配置错误');

        $class = __NAMESPACE__.'\\'.strtolower($source).'\\Api';
        if (! class_exists($class)) {
            $this->error('ACME 来源配置错误');
        }

        return new $class;
    }
}
