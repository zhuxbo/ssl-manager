<?php

declare(strict_types=1);

use App\Services\Delegation\Dns\DelegationDnsProviderFactory;
use App\Services\Delegation\Dns\DnsProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

test('未知远端错误码不进入可展示异常', function (string $provider) {
    $error = DnsProviderException::api($provider, '测试操作', 'secret-key https://example.com', 403);
    expect($error->getMessage())->toBe("$provider DNS 测试操作：服务商拒绝请求，HTTP 403")
        ->and($error->getPrevious())->toBeNull();
})->with(['Aliyun', 'Tencent', 'Cloudflare']);

test('三家网络失败只返回操作名和安全原因', function (string $provider, string $expected) {
    Http::fake(fn () => throw new ConnectionException('https://example.com/?AccessKeyId=secret&Signature=secret'));
    $dns = (new DelegationDnsProviderFactory)->make([
        'provider' => $provider, 'domain' => 'proxy.example.com',
        'accessKeyId' => 'secret', 'accessKeySecret' => 'secret',
        'secretId' => 'secret', 'secretKey' => 'secret',
        'zoneId' => 'zone', 'apiToken' => 'secret',
    ]);
    try {
        $dns->upsertTxt('label', ['value']);
        test()->fail('网络失败应抛出异常');
    } catch (DnsProviderException $e) {
        expect($e->getMessage())->toBe($expected)
            ->and($e->getPrevious())->toBeNull();
    }
})->with([
    ['aliyun', 'Aliyun DNS DescribeDomainRecords：网络连接失败或超时'],
    ['tencent', 'Tencent DNS CreateTXTRecord：网络连接失败或超时'],
    ['cloudflare', 'Cloudflare DNS 查询记录：网络连接失败或超时'],
]);
