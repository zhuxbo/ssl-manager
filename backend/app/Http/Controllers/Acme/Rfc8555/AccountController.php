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

        // 验证 URL
        $expectedUrl = rtrim(request()->getSchemeAndHttpHost(), '/').'/acme/new-acct';
        if (($protected['url'] ?? '') !== $expectedUrl) {
            return $this->acmeError('malformed', 'URL mismatch', 400);
        }

        // 只查询现有账户
        if ($payload['onlyReturnExisting'] ?? false) {
            $jwk = $this->jwsService->extractPublicKey($protected);
            if (! $jwk) {
                return $this->acmeError('malformed', 'Missing JWK', 400);
            }

            $keyId = $this->jwsService->computeKeyId($jwk);
            $account = $this->accountService->findByKeyId($keyId);

            if (! $account) {
                return $this->acmeError('accountDoesNotExist', 'Account not found', 400);
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

        // 验证 EAB HMAC 签名
        if (! $this->verifyEabSignature($eab, $order->eab_hmac)) {
            return $this->acmeError('unauthorized', 'Invalid EAB signature', 401);
        }

        // 首次使用时标记 eab_used_at（已有值则不覆盖）
        if (! $order->eab_used_at) {
            $order->update(['eab_used_at' => now()]);
        }

        // 提取 JWK 和联系方式
        $jwk = $this->jwsService->extractPublicKey($protected);
        if (! $jwk) {
            return $this->acmeError('malformed', 'Missing JWK', 400);
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

        $jws = $request->attributes->get('acme_jws');
        $payload = $jws['payload'];

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
    private function verifyEabSignature(array $eab, string $hmacKey): bool
    {
        $signingInput = $eab['protected'].'.'.$eab['payload'];
        $decodedHmac = $this->jwsService->base64UrlDecode($hmacKey);
        $expectedSignature = hash_hmac('sha256', $signingInput, $decodedHmac, true);

        return hash_equals($this->jwsService->base64UrlDecode($eab['signature']), $expectedSignature);
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
