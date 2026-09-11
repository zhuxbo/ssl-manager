<?php

declare(strict_types=1);

namespace App\Services\Delegation\Dns;

use RuntimeException;

/** 仅包含可展示的本地文案，不携带原始响应或请求异常。 */
class DnsProviderException extends RuntimeException
{
    public static function api(string $provider, string $operation, mixed $code, ?int $httpStatus = null): self
    {
        $reasons = match ($provider) {
            'Aliyun' => [
                'DomainRecordDuplicate' => '解析记录已存在',
                'DomainRecordConflict' => '与已有解析记录冲突',
                'DomainRecordLocked' => '解析记录被锁定',
                'DomainNotFound' => '域名不存在',
                'InvalidAccessKeyId.NotFound' => 'AccessKey ID 无效',
                'SignatureDoesNotMatch' => '签名校验失败',
                'Forbidden' => '无操作权限',
                'Throttling' => '请求频率超限',
            ],
            'Tencent' => [
                'AuthFailure.SecretIdNotFound' => 'SecretId 无效',
                'AuthFailure.SignatureFailure' => '签名校验失败',
                'AuthFailure.UnauthorizedOperation' => '无操作权限',
                'UnauthorizedOperation' => '无操作权限',
                'RequestLimitExceeded' => '请求频率超限',
                'InvalidParameter.DomainRecordExist' => '解析记录已存在',
                'ResourceNotFound.NoDataOfDomain' => '域名不存在',
            ],
            'Cloudflare' => [
                '10000' => '身份认证失败',
                '9109' => '无操作权限',
                '81057' => '解析记录已存在',
                '81053' => '与已有解析记录冲突',
            ],
            default => [],
        };
        // 未识别的远端字段可能包含敏感信息，不直接拼接。
        $key = is_string($code) || is_int($code) ? (string) $code : '';
        $reason = isset($reasons[$key]) ? $reasons[$key]."（{$key}）" : '服务商拒绝请求';
        $http = $httpStatus === null ? '' : "，HTTP $httpStatus";

        return new self("$provider DNS {$operation}：$reason$http");
    }
}
