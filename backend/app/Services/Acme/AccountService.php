<?php

declare(strict_types=1);

namespace App\Services\Acme;

use App\Models\Acme\Account;
use App\Models\Order;
use App\Models\User;

class AccountService
{
    public function __construct(
        private JwsService $jwsService
    ) {}

    /**
     * 创建或获取账户
     */
    public function createOrGet(array $jwk, array $contact, ?string $eabKid = null): array
    {
        $keyId = $this->jwsService->computeKeyId($jwk);

        $account = Account::where('key_id', $keyId)->first();

        if ($account) {
            return [
                'account' => $account,
                'created' => false,
            ];
        }

        // 查找 EAB 对应的用户
        $user = null;
        if ($eabKid) {
            $order = Order::where('eab_kid', $eabKid)->first();
            if ($order) {
                $user = User::find($order->user_id);
            }
        }

        if (! $user) {
            return [
                'error' => 'externalAccountRequired',
                'detail' => 'External account binding required',
            ];
        }

        try {
            $account = Account::create([
                'user_id' => $user->id,
                'order_id' => $order->id,
                'key_id' => $keyId,
                'public_key' => $jwk,
                'contact' => $contact,
                'status' => 'valid',
            ]);

            return [
                'account' => $account,
                'created' => true,
            ];
        } catch (\Illuminate\Database\QueryException $e) {
            // M6: 竞态条件 — unique 约束冲突时 fallback 查询
            $account = Account::where('key_id', $keyId)->first();
            if ($account) {
                return [
                    'account' => $account,
                    'created' => false,
                ];
            }
            throw $e;
        }
    }

    /**
     * 通过 key_id 查找账户
     */
    public function findByKeyId(string $keyId): ?Account
    {
        return Account::where('key_id', $keyId)->first();
    }

    /**
     * 通过用户 ID 查找账户
     */
    public function findByUserId(int $userId): ?Account
    {
        return Account::where('user_id', $userId)->first();
    }

    /**
     * 更新账户联系方式
     */
    public function updateContact(Account $account, array $contact): Account
    {
        $account->update(['contact' => $contact]);

        return $account->fresh();
    }

    /**
     * 停用账户
     */
    public function deactivate(Account $account): Account
    {
        $account->update(['status' => 'deactivated']);

        return $account->fresh();
    }

    /**
     * 生成账户 URL
     */
    public function getAccountUrl(Account $account): string
    {
        return url("/acme/acct/$account->key_id");
    }

    /**
     * 格式化账户响应
     */
    public function formatResponse(Account $account): array
    {
        return [
            'status' => $account->status,
            'contact' => $account->contact ?? [],
            'orders' => $this->getOrdersUrl($account),
        ];
    }

    /**
     * 生成订单列表 URL
     */
    private function getOrdersUrl(Account $account): string
    {
        return url("/acme/acct/$account->key_id/orders");
    }
}
