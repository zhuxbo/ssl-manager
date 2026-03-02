<?php

namespace App\Http\Controllers\Acme\Rfc8555;

use App\Http\Controllers\Controller;
use App\Services\Acme\NonceService;
use App\Services\Acme\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthorizationController extends Controller
{
    public function __construct(
        private OrderService $orderService,
        private NonceService $nonceService
    ) {}

    /**
     * POST /acme/authz/{token}
     * 获取授权详情
     */
    public function getAuthorization(Request $request, string $token): JsonResponse
    {
        $authorization = $this->orderService->getAuthorization($token);

        if (! $authorization) {
            return $this->acmeError('about:blank', 'Authorization not found', 404);
        }

        // POST 请求有 JWS 认证，验证归属；GET 请求依赖 token 不可猜测性
        $account = $request->attributes->get('acme_account');
        if ($request->isMethod('POST') && $authorization->cert && ! $this->orderService->verifyOwnership($authorization->cert, $account)) {
            return $this->acmeError('unauthorized', 'Authorization does not belong to this account', 403);
        }

        // 轮询时重新查询上游状态（challenge 已提交但 authz 仍为 pending）
        if ($authorization->status === 'pending' && $authorization->acme_challenge_id) {
            $this->orderService->respondToChallenge($authorization);
            $authorization->refresh();
        }

        $nonce = $this->nonceService->generate();

        return response()->json(
            $this->orderService->formatAuthorizationResponse($authorization),
            200,
            [
                'Replay-Nonce' => $nonce,
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
            'type' => $type === 'about:blank' ? 'about:blank' : "urn:ietf:params:acme:error:$type",
            'detail' => $detail,
            'status' => $status,
        ], $status, [
            'Replay-Nonce' => $nonce,
            'Content-Type' => 'application/problem+json',
        ]);
    }
}
