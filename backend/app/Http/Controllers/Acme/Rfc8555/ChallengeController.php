<?php

namespace App\Http\Controllers\Acme\Rfc8555;

use App\Http\Controllers\Controller;
use App\Services\Acme\NonceService;
use App\Services\Acme\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChallengeController extends Controller
{
    public function __construct(
        private OrderService $orderService,
        private NonceService $nonceService
    ) {}

    /**
     * POST /acme/chall/{token}
     * 响应验证挑战
     */
    public function respondToChallenge(Request $request, string $token): JsonResponse
    {
        $authorization = $this->orderService->getAuthorization($token);

        if (! $authorization) {
            return $this->acmeError('malformed', 'Challenge not found', 404);
        }

        $result = $this->orderService->respondToChallenge($authorization);
        $nonce = $this->nonceService->generate();

        $authorization->refresh();

        return response()->json(
            $this->orderService->formatChallengeResponse($authorization),
            200,
            [
                'Replay-Nonce' => $nonce,
                'Link' => '<'.$this->orderService->getAuthorizationUrl($authorization).'>;rel="up"',
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
