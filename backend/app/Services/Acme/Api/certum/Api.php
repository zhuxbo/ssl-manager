<?php

declare(strict_types=1);

namespace App\Services\Acme\Api\certum;

use App\Services\Acme\Api\AcmeSourceApiInterface;

class Api implements AcmeSourceApiInterface
{
    protected Sdk $sdk;

    public function __construct()
    {
        $this->sdk = new Sdk;
    }

    /**
     * 下单
     */
    public function new(array $data): array
    {
        return $this->sdk->new($data);
    }

    /**
     * 查询/同步订单信息
     */
    public function get(string|int $apiId, array $order = []): array
    {
        return $this->sdk->get($apiId);
    }

    /**
     * 取消订单
     */
    public function cancel(string|int $apiId, array $order = []): array
    {
        return $this->sdk->cancel($apiId);
    }

    /**
     * 获取产品列表
     */
    public function getProducts(string $brand = '', string $code = ''): array
    {
        return $this->sdk->getProducts($brand, $code);
    }
}
