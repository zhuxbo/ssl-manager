<?php

namespace App\Http\Controllers\Acme\Rfc8555;

use App\Http\Controllers\Controller;
use App\Services\Acme\NonceService;
use App\Services\Acme\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CertificateController extends Controller
{
    public function __construct(
        private OrderService $orderService,
        private NonceService $nonceService
    ) {}

    /**
     * POST /acme/cert/{token}
     * 下载证书
     */
    public function getCertificate(Request $request, string $token): Response|JsonResponse
    {
        $order = $this->orderService->get($token);

        if (!$order) {
            return $this->acmeError('malformed', 'Certificate not found', 404);
        }

        if ($order->status !== 'valid' || !$order->certificate) {
            return $this->acmeError('orderNotReady', 'Certificate not ready', 403);
        }

        $nonce = $this->nonceService->generate();

        // 返回证书链（证书 + 中间证书）
        $fullChain = $order->certificate;
        if ($order->chain) {
            $fullChain .= "\n" . $order->chain;
        }

        return response($fullChain, 200, [
            'Replay-Nonce' => $nonce,
            'Content-Type' => 'application/pem-certificate-chain',
        ]);
    }

    /**
     * POST /acme/revoke-cert
     * 吊销证书
     */
    public function revokeCertificate(Request $request): JsonResponse
    {
        $jws = $request->attributes->get('acme_jws');
        $payload = $jws['payload'];

        $certificate = $payload['certificate'] ?? '';
        $reason = $payload['reason'] ?? 0;

        if (empty($certificate)) {
            return $this->acmeError('malformed', 'Certificate is required', 400);
        }

        // TODO: 实现证书吊销逻辑
        // 需要查找证书、验证权限、调用上游吊销

        $nonce = $this->nonceService->generate();

        return response()->json(null, 200, [
            'Replay-Nonce' => $nonce,
        ]);
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
