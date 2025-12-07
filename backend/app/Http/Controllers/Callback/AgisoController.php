<?php

declare(strict_types=1);

namespace App\Http\Controllers\Callback;

use App\Http\Controllers\Controller;
use App\Models\Agiso;
use App\Models\Product;
use App\Models\ProductPrice;

class AgisoController extends Controller
{
    public function index(): void
    {
        $params = request()->all();
        $requiredParams = ['timestamp', 'aopic', 'sign', 'json'];
        if (! $this->hasRequiredParams($params, $requiredParams)) {
            $this->error('Missing required parameters');
        }

        if ($this->isValidSignature($params)) {
            $params['data'] = json_decode($params['json'], true);

            if (! isset($params['data']['Tid'])) {
                $this->error('Missing Tid');
            }

            if ($this->isRepeatOrder($params)) {
                $this->error('Repeat order');
            }

            $this->processOrder($params);
        } else {
            $this->error('Invalid signature');
        }
    }

    private function hasRequiredParams(array $params, array $requiredParams): bool
    {
        foreach ($requiredParams as $param) {
            if (! isset($params[$param])) {
                return false;
            }
        }

        return true;
    }

    private function isValidSignature(array $params): bool
    {
        $appSecret = get_system_setting('site', 'agisoAppSecret', '');
        $str = $appSecret.'json'.$params['json'].'timestamp'.$params['timestamp'].$appSecret;
        $create_sign = md5($str);

        return strcasecmp($params['sign'], $create_sign) == 0;
    }

    private function isRepeatOrder(array $params): bool
    {
        if (isset($params['data']['RefundId'])) {
            $order = Agiso::where([
                'type' => $params['aopic'],
                'refund_id' => $params['data']['RefundId'],
            ])->first();

            return $order !== null;
        }

        $order = Agiso::where([
            'type' => $params['aopic'],
            'tid' => $params['data']['Tid'],
        ])->first();

        return $order !== null;
    }

    private function processOrder(array $params): void
    {
        $platform = $params['fromPlatform'] ?? $params['data']['Platform'] ?? null;

        match ($platform) {
            'PddAlds' => $this->processPinduoduoOrder($params),
            'TbAlds' => $this->processTaobaoOrder($params),
            default => $this->error('Invalid platform'),
        };
    }

    private function processPinduoduoOrder(array $params): void
    {
        $totalPrice = '0.00';
        $count = 0;
        $firstProductCode = null;
        $firstPeriod = null;
        $isMixed = false; // 标记是否为混合商品订单

        foreach (($params['data']['ItemList'] ?? []) as $item) {
            $itemCount = (int) $item['goods_count'];
            $count += $itemCount;

            // 提取 outer_id 并解析 product_code 和 period
            if (! empty($item['outer_id'])) {
                $result = $this->processItemSku($item['outer_id'], $itemCount);
                if ($result) {
                    // 检查是否为混合商品
                    if (! $firstProductCode) {
                        $firstProductCode = $result['productCode'];
                        $firstPeriod = $result['period'];
                    } elseif ($firstProductCode !== $result['productCode'] || $firstPeriod !== $result['period']) {
                        $isMixed = true;
                    }

                    $totalPrice = bcadd($totalPrice, $result['itemTotal'], 2);
                }
            }
        }

        if ($totalPrice !== '0.00') {
            Agiso::create([
                'platform' => $params['fromPlatform'] ?? $params['data']['Platform'] ?? null,
                'sign' => $params['sign'],
                'timestamp' => $params['timestamp'],
                'type' => $params['aopic'],
                'data' => $params['json'],
                'tid' => (string) $params['data']['Tid'],
                'product_code' => $isMixed ? null : $firstProductCode,
                'period' => $isMixed ? null : $firstPeriod,
                'price' => $totalPrice,
                'count' => $count,
                'amount' => (string) $params['data']['PayAmount'] ?? '0.00',
            ]);
        }

        $this->success();
    }

    private function processTaobaoOrder(array $params): void
    {
        $amount = '0.00';
        $totalPrice = '0.00';
        $count = 0;
        $firstProductCode = null;
        $firstPeriod = null;
        $isMixed = false; // 标记是否为混合商品订单

        foreach (($params['data']['Orders'] ?? []) as $order) {
            $amount = bcadd($amount, (string) $order['Payment'], 2);
            $itemCount = (int) $order['Num'];
            $count += $itemCount;

            // 提取 OuterSkuId 并解析 product_code 和 period
            if (! empty($order['OuterSkuId'])) {
                $result = $this->processItemSku($order['OuterSkuId'], $itemCount);
                if ($result) {
                    // 检查是否为混合商品
                    if (! $firstProductCode) {
                        $firstProductCode = $result['productCode'];
                        $firstPeriod = $result['period'];
                    } elseif ($firstProductCode !== $result['productCode'] || $firstPeriod !== $result['period']) {
                        $isMixed = true;
                    }

                    $totalPrice = bcadd($totalPrice, $result['itemTotal'], 2);
                }
            }
        }

        if ($totalPrice !== '0.00') {
            Agiso::create([
                'platform' => $params['fromPlatform'] ?? $params['data']['Platform'] ?? null,
                'sign' => $params['sign'],
                'timestamp' => $params['timestamp'],
                'type' => $params['aopic'],
                'data' => $params['json'],
                'tid' => (string) $params['data']['Tid'],
                'status' => $params['data']['Status'] ?? null,
                'product_code' => $isMixed ? null : $firstProductCode,
                'period' => $isMixed ? null : $firstPeriod,
                'price' => $totalPrice,
                'count' => $count,
                'amount' => $amount,
            ]);
        }

        $this->success();
    }

    /**
     * 处理商品 SKU，解析 product_code 和 period，计算价格
     *
     * @return array{productCode: string, period: int, itemTotal: string}|null
     */
    private function processItemSku(string $skuId, int $itemCount): ?array
    {
        $parts = explode('#', $skuId);
        if (count($parts) !== 2) {
            return null;
        }

        $productCode = $parts[0];
        $period = (int) $parts[1] ?? 1;

        // 查询单价并计算该商品的总价
        $itemTotal = '0.00';
        $unitPrice = $this->getProductPrice($productCode, $period);
        if ($unitPrice) {
            $itemTotal = bcmul($unitPrice, (string) $itemCount, 2);
        }

        return [
            'productCode' => $productCode,
            'period' => $period,
            'itemTotal' => $itemTotal,
        ];
    }

    /**
     * 根据 product_code 和 period 查询 platinum 级别的价格
     */
    private function getProductPrice(string $productCode, int $period): ?string
    {
        $product = Product::where('code', $productCode)->first();
        if (! $product) {
            return null;
        }

        $productPrice = ProductPrice::where('product_id', $product->id)
            ->where('level_code', 'platinum')
            ->where('period', $period)
            ->first();

        return $productPrice ? (string) $productPrice->price : null;
    }
}
