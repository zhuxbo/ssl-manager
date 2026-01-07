<?php

namespace App\Http\Controllers\Acme\Rfc8555;

use App\Http\Controllers\Controller;
use App\Services\Acme\NonceService;
use App\Services\Acme\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService,
        private NonceService $nonceService
    ) {}

    /**
     * POST /acme/new-order
     * 创建新订单
     */
    public function newOrder(Request $request): JsonResponse
    {
        $account = $request->attributes->get('acme_account');
        $jws = $request->attributes->get('acme_jws');
        $payload = $jws['payload'];

        $identifiers = $payload['identifiers'] ?? [];

        if (empty($identifiers)) {
            return $this->acmeError('malformed', 'No identifiers provided', 400);
        }

        // 验证标识符格式
        foreach ($identifiers as $identifier) {
            if (!isset($identifier['type'], $identifier['value'])) {
                return $this->acmeError('malformed', 'Invalid identifier format', 400);
            }

            if ($identifier['type'] !== 'dns') {
                return $this->acmeError('unsupportedIdentifier', "Unsupported identifier type: {$identifier['type']}", 400);
            }
        }

        $result = $this->orderService->create($account, $identifiers);

        if (isset($result['error'])) {
            return $this->acmeError($result['error'], $result['detail'], 403);
        }

        $order = $result['order'];
        $nonce = $this->nonceService->generate();
        $orderUrl = $this->orderService->getOrderUrl($order);

        return response()->json(
            $this->orderService->formatOrderResponse($order),
            201,
            [
                'Replay-Nonce' => $nonce,
                'Location' => $orderUrl,
                'Content-Type' => 'application/json',
            ]
        );
    }

    /**
     * POST /acme/order/{token}
     * 获取订单详情
     */
    public function getOrder(Request $request, string $token): JsonResponse
    {
        $order = $this->orderService->get($token);

        if (!$order) {
            return $this->acmeError('malformed', 'Order not found', 404);
        }

        $nonce = $this->nonceService->generate();

        return response()->json(
            $this->orderService->formatOrderResponse($order),
            200,
            [
                'Replay-Nonce' => $nonce,
                'Content-Type' => 'application/json',
            ]
        );
    }

    /**
     * POST /acme/order/{token}/finalize
     * 完成订单
     */
    public function finalizeOrder(Request $request, string $token): JsonResponse
    {
        $order = $this->orderService->get($token);

        if (!$order) {
            return $this->acmeError('malformed', 'Order not found', 404);
        }

        $jws = $request->attributes->get('acme_jws');
        $payload = $jws['payload'];
        $csr = $payload['csr'] ?? '';

        if (empty($csr)) {
            return $this->acmeError('badCSR', 'CSR is required', 400);
        }

        $result = $this->orderService->finalize($order, $csr);

        if (isset($result['error'])) {
            return $this->acmeError($result['error'], $result['detail'], 403);
        }

        $order = $result['order'];
        $nonce = $this->nonceService->generate();
        $orderUrl = $this->orderService->getOrderUrl($order);

        return response()->json(
            $this->orderService->formatOrderResponse($order),
            200,
            [
                'Replay-Nonce' => $nonce,
                'Location' => $orderUrl,
                'Content-Type' => 'application/json',
            ]
        );
    }

    /**
     * 返回 ACME 错误
     */
    private function acmeError(string $type, string $detail, int $status): JsonResponse
    {
        $nonce = $this->nonceService->generate();

        return response()->json([
            'type' => "urn:ietf:params:acme:error:$type",
            'detail' => $detail,
            'status' => $status,
        ], $status, [
            'Replay-Nonce' => $nonce,
            'Content-Type' => 'application/problem+json',
        ]);
    }
}
