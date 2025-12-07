<?php

declare(strict_types=1);

namespace App\Services\Order\Utils;

use App\Models\Order;

class FilterUtil
{
    private const array FILTER_FIELDS = [
        // SSL 证书
        'new' => [
            'is_batch',
            'plus',
            'unique_value',
            'user_id',
            'product_id',
            'period',
            'validation_method',
            'domains',
            'contact',
            'organization',
        ],
        'renew' => [
            'order_id',
            'unique_value',
            'period',
            'validation_method',
            'domains',
            'contact',
            'organization',
        ],
        'reissue' => [
            'order_id',
            'unique_value',
            'validation_method',
            'domains',
        ],
        // SMIME 证书
        'new_smime' => [
            'user_id',
            'product_id',
            'period',
            'email',
            'contact',
            'organization',
        ],
        'renew_smime' => [
            'order_id',
            'period',
            'email',
            'contact',
            'organization',
        ],
        'reissue_smime' => [
            'order_id',
            'email',
        ],
        // CodeSign 证书（有 CSR，无域名验证）
        'new_codesign' => [
            'user_id',
            'product_id',
            'period',
            'contact',
            'organization',
        ],
        'renew_codesign' => [
            'order_id',
            'period',
            'contact',
            'organization',
        ],
        'reissue_codesign' => ['order_id'],
        'organization' => [
            'name',
            'registration_number',
            'phone',
            'address',
            'city',
            'state',
            'country',
            'postcode',
        ],
        'contact' => [
            'first_name',
            'last_name',
            'title',
            'email',
            'phone',
        ],
    ];

    public static function getProductType(array $params): string
    {
        $productId = (int) ($params['product_id'] ?? 0);
        $orderId = $params['order_id'] ?? null;

        // 续费或重签时从订单中获取产品 仅用于获取产品类型 之后会重新查询产品
        if ($orderId && in_array($params['action'] ?? '', ['renew', 'reissue'])) {
            $order = Order::with('product')->find($orderId);
            $product = $order?->product;
        } else {
            $product = $productId ? FindUtil::Product($productId) : null;
        }

        return $product?->product_type ?? 'ssl';
    }

    public static function filterParamsField(array $params): array
    {
        $action = $params['action'] ?? 'new';
        $productType = self::getProductType($params);

        // 根据产品类型选择过滤配置
        $key = $productType === 'ssl' ? $action : "{$action}_$productType";
        $allowedFields = self::FILTER_FIELDS[$key] ?? self::FILTER_FIELDS[$action] ?? self::FILTER_FIELDS['new'];

        // 添加必需的字段
        $allowedFields = array_merge($allowedFields, [
            'action',
            'channel',
            'refer_id',
            'csr_generate',
            'encryption',
            'csr',
        ]);

        $params = self::arrayFilterAllowedKeys($params, $allowedFields);

        if (isset($params['organization'])) {
            $params['organization'] = self::filterOrganization($params['organization']);
        }
        if (isset($params['contact'])) {
            $params['contact'] = self::filterContact($params['contact']);
        }

        return $params;
    }

    public static function filterOrganization(array|int|string $organization): array|int
    {
        if (is_int($organization) || is_string($organization)) {
            return (int) $organization;
        }

        return self::arrayFilterAllowedKeys($organization, self::FILTER_FIELDS['organization']);
    }

    public static function filterContact(array|int|string $contact): array|int
    {
        if (is_int($contact) || is_string($contact)) {
            return (int) $contact;
        }

        return self::arrayFilterAllowedKeys($contact, self::FILTER_FIELDS['contact']);
    }

    public static function arrayFilterAllowedKeys(array $data, array|string $allowedFields = []): array
    {
        $allowedFields = is_array($allowedFields) ? $allowedFields : explode(',', $allowedFields);

        return array_filter($data, function ($key) use ($allowedFields) {
            return in_array($key, $allowedFields);
        }, ARRAY_FILTER_USE_KEY);
    }
}
