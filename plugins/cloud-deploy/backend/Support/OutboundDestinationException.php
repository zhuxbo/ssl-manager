<?php

declare(strict_types=1);

namespace Plugins\CloudDeploy\Support;

use RuntimeException;

class OutboundDestinationException extends RuntimeException
{
    public function __construct(private readonly string $reasonCode)
    {
        $reason = match ($reasonCode) {
            'dns_resolution_failed' => '目标域名 DNS 解析失败',
            'forbidden_address' => '目标解析到禁止访问的 IP 地址',
            'mixed_address_scope' => '目标同时解析到公网和私网地址',
            'private_not_allowed' => '目标私网地址未获授权',
            'scheme_not_allowed' => '目标地址协议不受支持',
            'userinfo_not_allowed' => '目标地址不能包含用户信息',
            'fragment_not_allowed' => '目标地址不能包含片段标识',
            'invalid_official_host', 'invalid_host' => '目标域名格式无效',
            'endpoint_not_allowed' => '目标端点不在允许范围内',
            default => '目标地址无效',
        };
        parent::__construct('部署目标出站校验失败：'.$reason);
    }

    public function reasonCode(): string
    {
        return $this->reasonCode;
    }
}
