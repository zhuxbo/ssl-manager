<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Models\Order;
use App\Models\User;
use App\Services\Delegation\CnameDelegationService;

/**
 * 自动续费/重签判定服务
 * 集中处理自动续费和重签的判定逻辑
 */
class AutoRenewService
{
    public function __construct(
        private readonly CnameDelegationService $delegationService
    ) {}

    /**
     * 检查自动续费是否会实际执行
     *
     * 条件：
     * - auto_renew = true（订单级或用户级）
     * - 产品 status=1 且 renew=1
     * - 证书 channel != 'acme'
     * - period_till - expires_at < 7天（订单周期与证书到期接近）
     */
    public function willAutoRenewExecute(Order $order, User $user): bool
    {
        // 检查 auto_renew 设置
        $autoRenewEnabled = $order->auto_renew ?? ($user->auto_settings['auto_renew'] ?? false);
        if (! $autoRenewEnabled) {
            return false;
        }

        // 检查产品是否支持续费
        $product = $order->product;
        if (! $product || $product->status != 1 || ! $product->renew) {
            return false;
        }

        // 检查证书 channel 不是 acme
        $cert = $order->latestCert;
        if ($cert->channel === 'acme') {
            return false;
        }

        // 检查订单周期与证书到期时间相差小于7天
        $periodTill = $order->period_till;
        $expiresAt = $cert->expires_at;
        if ($periodTill && $expiresAt) {
            $daysDiff = $periodTill->diffInDays($expiresAt, false);
            if ($daysDiff >= 7) {
                return false;
            }
        }

        return true;
    }

    /**
     * 检查自动重签是否会实际执行
     *
     * 条件：
     * - auto_reissue = true（订单级或用户级）
     * - 产品 status=1
     * - 证书 channel != 'acme'
     * - period_till - expires_at > 7天（订单周期还有余量）
     */
    public function willAutoReissueExecute(Order $order, User $user): bool
    {
        // 检查 auto_reissue 设置
        $autoReissueEnabled = $order->auto_reissue ?? ($user->auto_settings['auto_reissue'] ?? false);
        if (! $autoReissueEnabled) {
            return false;
        }

        // 检查产品状态
        $product = $order->product;
        if (! $product || $product->status != 1) {
            return false;
        }

        // 检查证书 channel 不是 acme
        $cert = $order->latestCert;
        if ($cert->channel === 'acme') {
            return false;
        }

        // 检查订单周期与证书到期时间相差大于7天
        $periodTill = $order->period_till;
        $expiresAt = $cert->expires_at;
        if ($periodTill && $expiresAt) {
            $daysDiff = $periodTill->diffInDays($expiresAt, false);
            if ($daysDiff <= 7) {
                return false;
            }
        }

        return true;
    }

    /**
     * 检查所有域名是否都有有效委托记录（即时验证）
     *
     * @param  int  $userId  用户ID
     * @param  string  $domains  域名列表（逗号分隔）
     * @param  string  $ca  CA名称
     * @return bool 是否所有域名都有有效委托
     */
    public function checkDelegationValidity(int $userId, string $domains, string $ca): bool
    {
        $prefix = CnameDelegationService::getDelegationPrefixForCa($ca);
        $domainList = explode(',', trim($domains, ','));

        foreach ($domainList as $domain) {
            $domain = trim($domain);
            if (empty($domain)) {
                continue;
            }

            // 查找委托记录（不检查 valid 状态）
            $delegation = $this->delegationService->findDelegation($userId, $domain, $prefix);

            if (! $delegation) {
                return false;
            }

            // 即时验证 CNAME 记录
            if (! $this->delegationService->checkAndUpdateValidity($delegation)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 检查订单是否启用了自动续费
     *
     * @param  Order  $order  订单
     * @param  User  $user  用户
     * @return bool 是否启用
     */
    public function isAutoRenewEnabled(Order $order, User $user): bool
    {
        return $order->auto_renew ?? ($user->auto_settings['auto_renew'] ?? false);
    }
}
