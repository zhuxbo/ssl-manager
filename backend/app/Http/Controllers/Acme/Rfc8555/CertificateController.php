<?php

namespace App\Http\Controllers\Acme\Rfc8555;

use App\Http\Controllers\Controller;
use App\Models\Cert;
use App\Services\Acme\AcmeApiClient;
use App\Services\Acme\NonceService;
use App\Services\Acme\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CertificateController extends Controller
{
    public function __construct(
        private OrderService $orderService,
        private NonceService $nonceService,
        private AcmeApiClient $apiClient
    ) {}

    /**
     * POST /acme/cert/{referId}
     * 下载证书
     */
    public function getCertificate(Request $request, string $referId): Response|JsonResponse
    {
        $cert = $this->orderService->get($referId);

        if (! $cert) {
            return $this->acmeError('malformed', 'Certificate not found', 404);
        }

        if (! $cert->cert) {
            return $this->acmeError('orderNotReady', 'Certificate not ready', 403);
        }

        $nonce = $this->nonceService->generate();

        // 返回证书链（证书 + 中间证书）
        $fullChain = $cert->cert;
        if ($cert->intermediate_cert) {
            $fullChain .= "\n".$cert->intermediate_cert;
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

        if (empty($certificate)) {
            return $this->acmeError('malformed', 'Certificate is required', 400);
        }

        // base64url 解码证书
        $certDer = base64_decode(strtr($certificate, '-_', '+/'));
        $certPem = "-----BEGIN CERTIFICATE-----\n"
            .chunk_split(base64_encode($certDer), 64, "\n")
            .'-----END CERTIFICATE-----';

        // 解析证书获取序列号
        $certParsed = openssl_x509_parse($certPem);
        $serialNumber = $certParsed['serialNumberHex'] ?? null;

        if (! $serialNumber) {
            return $this->acmeError('malformed', 'Unable to parse certificate', 400);
        }

        // 在 certs 表查找
        $cert = Cert::where('channel', 'acme')
            ->where('serial_number', $serialNumber)
            ->first();

        if (! $cert) {
            return $this->acmeError('malformed', 'Certificate not found', 404);
        }

        // 调用连接的 ACME 服务吊销
        if ($this->apiClient->isConfigured() && $cert->serial_number) {
            $result = $this->apiClient->revokeCertificate($cert->serial_number);

            if ($result['code'] !== 1) {
                return $this->acmeError('serverInternal', $result['msg'] ?? 'Revocation failed', 500);
            }
        }

        // 更新 cert 状态
        $cert->update(['status' => 'revoked']);

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
