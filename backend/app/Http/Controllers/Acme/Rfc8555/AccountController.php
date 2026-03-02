<?php

namespace App\Http\Controllers\Acme\Rfc8555;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Acme\AccountService;
use App\Services\Acme\JwsService;
use App\Services\Acme\NonceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function __construct(
        private AccountService $accountService,
        private JwsService $jwsService,
        private NonceService $nonceService
    ) {}

    /**
     * POST /acme/new-acct
     * 注册新账户或获取已有账户
     */
    public function newAccount(Request $request): JsonResponse
    {
        $jws = $request->attributes->get('acme_jws');
        $payload = $jws['payload'];
        $protected = $jws['protected'];

        // 只查询现有账户
        if ($payload['onlyReturnExisting'] ?? false) {
            $jwk = $this->jwsService->extractPublicKey($protected);
            if (! $jwk) {
                return $this->acmeError('malformed', 'Missing JWK', 400);
            }

            $keyId = $this->jwsService->computeKeyId($jwk);
            $account = $this->accountService->findByKeyId($keyId);

            if (! $account) {
                return $this->acmeError('accountDoesNotExist', 'Account not found', 404);
            }

            return $this->accountResponse($account, 200);
        }

        // 验证 EAB
        $eab = $payload['externalAccountBinding'] ?? null;
        if (! $eab) {
            return $this->acmeError('externalAccountRequired', 'External account binding required', 400);
        }

        // 解析 EAB
        $eabProtected = json_decode($this->jwsService->base64UrlDecode($eab['protected']), true);
        $eabKid = $eabProtected['kid'] ?? null;

        if (! $eabKid) {
            return $this->acmeError('malformed', 'Invalid EAB kid', 400);
        }

        // 查找 EAB 对应的订单（EAB 可复用，不拒绝已使用的 EAB）
        $order = Order::where('eab_kid', $eabKid)->first();

        if (! $order) {
            return $this->acmeError('unauthorized', 'Invalid EAB credentials', 401);
        }

        // 提取 JWK（EAB 校验需要）
        $jwk = $this->jwsService->extractPublicKey($protected);
        if (! $jwk) {
            return $this->acmeError('malformed', 'Missing JWK', 400);
        }

        // 验证 EAB HMAC 签名（含 alg/url/payload 校验）
        if (! $this->verifyEabSignature($eab, $order->eab_hmac, $jwk)) {
            return $this->acmeError('unauthorized', 'Invalid EAB signature', 401);
        }

        // 首次使用时标记 eab_used_at（已有值则不覆盖）
        if (! $order->eab_used_at) {
            $order->update(['eab_used_at' => now()]);
        }

        $contact = $payload['contact'] ?? [];

        // 创建或获取账户
        $result = $this->accountService->createOrGet($jwk, $contact, $eabKid);

        if (isset($result['error'])) {
            return $this->acmeError($result['error'], $result['detail'], 400);
        }

        $status = $result['created'] ? 201 : 200;

        return $this->accountResponse($result['account'], $status);
    }

    /**
     * POST /acme/acct/{keyId}
     * 更新账户
     */
    public function updateAccount(Request $request, string $keyId): JsonResponse
    {
        $account = $request->attributes->get('acme_account');

        if ($account->key_id !== $keyId) {
            return $this->acmeError('unauthorized', 'Account mismatch', 401);
        }

        // M4: 停用账户不允许更新（停用请求除外）
        $jws = $request->attributes->get('acme_jws');
        $payload = $jws['payload'];

        if ($account->status !== 'valid' && ($payload['status'] ?? '') !== 'deactivated') {
            return $this->acmeError('unauthorized', 'Account is deactivated', 401);
        }

        // 更新联系方式
        if (isset($payload['contact'])) {
            $account = $this->accountService->updateContact($account, $payload['contact']);
        }

        // 停用账户
        if (($payload['status'] ?? '') === 'deactivated') {
            $account = $this->accountService->deactivate($account);
        }

        return $this->accountResponse($account, 200);
    }

    /**
     * 验证 EAB HMAC 签名
     */
    /**
     * 验证 EAB HMAC 签名（RFC 8555 §7.3.4）
     *
     * @param  array  $eab  EAB JWS 对象
     * @param  string  $hmacKey  Base64url 编码的 HMAC 密钥
     * @param  array  $outerJwk  外层 JWS 的 JWK（用于 payload 比对）
     */
    private function verifyEabSignature(array $eab, string $hmacKey, array $outerJwk): bool
    {
        // S2: 解码 EAB protected header，校验 alg 和 url
        $eabProtected = json_decode($this->jwsService->base64UrlDecode($eab['protected']), true);

        // 校验 alg === 'HS256'
        if (($eabProtected['alg'] ?? '') !== 'HS256') {
            return false;
        }

        // 校验 url 为当前 new-acct 端点
        $expectedUrl = url('/acme/new-acct');
        if (($eabProtected['url'] ?? '') !== $expectedUrl) {
            return false;
        }

        // 验证 HMAC 签名
        $signingInput = $eab['protected'].'.'.$eab['payload'];
        $decodedHmac = $this->jwsService->base64UrlDecode($hmacKey);
        $expectedSignature = hash_hmac('sha256', $signingInput, $decodedHmac, true);

        if (! hash_equals($this->jwsService->base64UrlDecode($eab['signature']), $expectedSignature)) {
            return false;
        }

        // S3: 比对 EAB payload 与外层 JWK 是否一致（规范化键值比对，避免依赖 JSON 键顺序）
        $eabPayload = json_decode($this->jwsService->base64UrlDecode($eab['payload']), true);
        if (! $eabPayload) {
            return false;
        }

        $requiredKeys = match ($outerJwk['kty'] ?? '') {
            'RSA' => ['e', 'kty', 'n'],
            'EC' => ['crv', 'kty', 'x', 'y'],
            default => array_keys($outerJwk),
        };
        sort($requiredKeys);

        $eabFiltered = [];
        $jwkFiltered = [];
        foreach ($requiredKeys as $key) {
            $eabFiltered[$key] = $eabPayload[$key] ?? null;
            $jwkFiltered[$key] = $outerJwk[$key] ?? null;
        }

        if ($eabFiltered !== $jwkFiltered) {
            return false;
        }

        return true;
    }

    /**
     * 返回账户响应
     */
    private function accountResponse($account, int $status): JsonResponse
    {
        $nonce = $this->nonceService->generate();
        $accountUrl = $this->accountService->getAccountUrl($account);

        return response()->json(
            $this->accountService->formatResponse($account),
            $status,
            [
                'Replay-Nonce' => $nonce,
                'Location' => $accountUrl,
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
