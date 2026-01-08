<?php

namespace App\Http\Middleware;

use App\Services\Acme\AccountService;
use App\Services\Acme\JwsService;
use App\Services\Acme\NonceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AcmeJwsMiddleware
{
    public function __construct(
        private JwsService $jwsService,
        private NonceService $nonceService,
        private AccountService $accountService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // 跳过 GET/HEAD 请求（如 directory、new-nonce）
        if (in_array($request->method(), ['GET', 'HEAD'])) {
            return $next($request);
        }

        // 解析 JWS
        $body = $request->getContent();
        $jws = $this->jwsService->parse($body);

        if (!$jws) {
            return $this->acmeError('malformed', 'Invalid JWS format', 400);
        }

        $protected = $jws['protected'];

        // 验证 Nonce
        $nonce = $protected['nonce'] ?? null;
        if (!$nonce || !$this->nonceService->verify($nonce)) {
            return $this->acmeError('badNonce', 'Invalid or expired nonce', 400);
        }

        // 验证 URL
        $url = $protected['url'] ?? '';
        if (!$url || $url !== $request->fullUrl()) {
            return $this->acmeError('malformed', 'URL mismatch', 400);
        }

        // 获取公钥（从 JWK 或 KID）
        $jwk = $this->jwsService->extractPublicKey($protected);
        $kid = $this->jwsService->extractKid($protected);

        $account = null;

        if ($kid) {
            // 使用 KID 查找账户
            $account = $this->jwsService->findAccountByKid($kid);

            if (!$account) {
                return $this->acmeError('accountDoesNotExist', 'Account not found', 400);
            }

            // 检查账户状态
            if ($account->status !== 'valid') {
                return $this->acmeError('unauthorized', 'Account is deactivated or revoked', 401);
            }

            $jwk = $account->public_key;
        } elseif (!$jwk) {
            return $this->acmeError('malformed', 'Missing JWK or KID', 400);
        }

        // 验证签名
        if (!$this->jwsService->verify($jws, $jwk)) {
            return $this->acmeError('badSignatureAlgorithm', 'Invalid signature', 400);
        }

        // 如果通过 JWK 验证，尝试查找账户
        if (!$account && $jwk) {
            $keyId = $this->jwsService->computeKeyId($jwk);
            $account = $this->accountService->findByKeyId($keyId);
        }

        // 将解析结果存入请求
        $request->attributes->set('acme_jws', $jws);
        $request->attributes->set('acme_account', $account);
        $request->attributes->set('acme_jwk', $jwk);

        return $next($request);
    }

    private function acmeError(string $type, string $detail, int $status): Response
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
