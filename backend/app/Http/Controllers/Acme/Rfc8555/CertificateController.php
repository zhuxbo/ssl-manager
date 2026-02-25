<?php

namespace App\Http\Controllers\Acme\Rfc8555;

use App\Http\Controllers\Controller;
use App\Models\Cert;
use App\Services\Acme\ApiClient;
use App\Services\Acme\JwsService;
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
        private ApiClient $apiClient,
        private JwsService $jwsService
    ) {}

    /**
     * POST /acme/cert/{referId}
     * 下载证书
     */
    public function getCertificate(Request $request, string $referId): Response|JsonResponse
    {
        $cert = $this->orderService->get($referId);

        if (! $cert) {
            return $this->acmeError('about:blank', 'Certificate not found', 404);
        }

        // POST 请求有 JWS 认证，验证归属；GET 请求依赖 referId 不可猜测性
        $account = $request->attributes->get('acme_account');
        if ($request->isMethod('POST') && ! $this->orderService->verifyOwnership($cert, $account)) {
            return $this->acmeError('unauthorized', 'Certificate does not belong to this account', 403);
        }

        if (! $cert->cert) {
            // processing 状态且有 CSR，尝试从上游获取证书
            if ($cert->csr && $this->orderService->tryCompletePendingFinalize($cert)) {
                $cert->refresh();
            }

            if (! $cert->cert) {
                return $this->acmeError('orderNotReady', 'Certificate not ready', 403);
            }
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
    public function revokeCertificate(Request $request): Response|JsonResponse
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
            return $this->acmeError('about:blank', 'Certificate not found', 404);
        }

        // 验证证书归属
        $account = $request->attributes->get('acme_account');
        if ($account) {
            // KID 模式：校验账户归属
            if ($cert->order && $cert->order->user_id !== $account->user_id) {
                return $this->acmeError('unauthorized', 'Certificate does not belong to this account', 403);
            }
        } else {
            // JWK 模式：比对 JWK 公钥与证书公钥
            $jwk = $request->attributes->get('acme_jwk');
            if ($jwk) {
                $jwkPem = $this->jwsService->jwkToPem($jwk);
                if (! $jwkPem) {
                    return $this->acmeError('unauthorized', 'Invalid JWK', 403);
                }

                $certPubKey = openssl_pkey_get_public($certPem);
                if (! $certPubKey) {
                    return $this->acmeError('unauthorized', 'Cannot extract public key from certificate', 403);
                }

                $certPubKeyDetails = openssl_pkey_get_details($certPubKey);
                $jwkPubKey = openssl_pkey_get_public($jwkPem);
                $jwkPubKeyDetails = $jwkPubKey ? openssl_pkey_get_details($jwkPubKey) : null;

                if (! $jwkPubKeyDetails || ($certPubKeyDetails['key'] ?? '') !== ($jwkPubKeyDetails['key'] ?? '')) {
                    return $this->acmeError('unauthorized', 'JWK does not match certificate public key', 403);
                }
            }
        }

        // 调用连接的 ACME 服务吊销
        if ($this->apiClient->isConfigured() && $cert->serial_number) {
            $result = $this->apiClient->revokeCertificate($cert->serial_number);

            if ($result['code'] !== 1) {
                $msg = $result['msg'] ?? 'Revocation failed';
                // "already revoked" 视为成功
                if (! str_contains(strtolower($msg), 'already revoked')) {
                    return $this->acmeError('serverInternal', $msg, 500);
                }
            }
        }

        // 更新 cert 状态
        $cert->update(['status' => 'revoked']);

        $nonce = $this->nonceService->generate();

        return response('', 200, [
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
            'type' => $type === 'about:blank' ? 'about:blank' : "urn:ietf:params:acme:error:$type",
            'detail' => $detail,
            'status' => $status,
        ], $status, [
            'Replay-Nonce' => $nonce,
            'Content-Type' => 'application/problem+json',
        ]);
    }
}
