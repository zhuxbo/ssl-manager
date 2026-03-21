<?php

declare(strict_types=1);

namespace App\Services\Acme\Api;

interface AcmeSourceApiInterface
{
    /**
     * 下单
     */
    public function new(array $data): array;

    /**
     * 查询/同步（返回含 EAB）
     */
    public function get(string|int $apiId, array $order = []): array;

    /**
     * 取消（内部处理已签发->吊销）
     */
    public function cancel(string|int $apiId, array $order = []): array;

    /**
     * 获取产品列表
     */
    public function getProducts(string $brand = '', string $code = ''): array;
}
