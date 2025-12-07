<?php

declare(strict_types=1);

namespace App\Services\Delegation;

use App\Traits\ApiResponse;

/**
 * 委托验证 DNS 管理服务
 * 负责管理委托验证过程中的 DNS TXT 记录设置和清理
 */
class DelegationDnsService
{
    use ApiResponse;

    protected ProxyDNS $proxyDNS;

    public function __construct()
    {
        $this->proxyDNS = new ProxyDNS;
    }

    /**
     * 按 label 直接设置 TXT 记录（用于自动委托验证）
     *
     * @param  string  $proxyZone  代理域名
     * @param  string  $label  哈希标签
     * @param  array  $values  TXT 值数组
     * @return bool 是否成功设置
     */
    public function setTxtByLabel(string $proxyZone, string $label, array $values): bool
    {
        if (empty($proxyZone) || empty($label) || empty($values)) {
            return false;
        }

        // 调用 DNS API 设置 TXT 记录
        return $this->proxyDNS->upsertTXT(
            zone: $proxyZone,
            name: $label,
            values: $values
        );
    }

    /**
     * 按 label 删除 TXT 记录
     *
     * @param  string  $proxyZone  代理域名
     * @param  string  $label  哈希标签
     * @return bool 是否成功删除
     */
    public function deleteTxtByLabel(string $proxyZone, string $label): void
    {
        if (empty($proxyZone) || empty($label)) {
            return;
        }

        // 调用 DNS API 删除 TXT 记录
        $this->proxyDNS->deleteTXT($proxyZone, $label);
    }
}
