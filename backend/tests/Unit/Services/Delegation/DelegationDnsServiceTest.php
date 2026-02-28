<?php

use App\Services\Delegation\DelegationDnsService;
use App\Services\Delegation\ProxyDNS;
afterEach(function () {
    Mockery::close();
});

test('setTxtByLabel 成功设置 TXT 记录', function () {
    $proxyDNS = Mockery::mock(ProxyDNS::class);
    $proxyDNS->shouldReceive('upsertTXT')
        ->once()
        ->with('proxy.example.com', 'abc123hash', ['challenge-value-1'])
        ->andReturn(true);

    $service = new DelegationDnsService;

    // 通过反射注入 Mock ProxyDNS
    $reflection = new ReflectionClass($service);
    $property = $reflection->getProperty('proxyDNS');
    $property->setValue($service, $proxyDNS);

    $result = $service->setTxtByLabel('proxy.example.com', 'abc123hash', ['challenge-value-1']);

    expect($result)->toBeTrue();
});

test('setTxtByLabel 参数为空时返回 false', function () {
    $service = new DelegationDnsService;

    // proxyZone 为空
    expect($service->setTxtByLabel('', 'label', ['value']))->toBeFalse();
    // label 为空
    expect($service->setTxtByLabel('proxy.example.com', '', ['value']))->toBeFalse();
    // values 为空
    expect($service->setTxtByLabel('proxy.example.com', 'label', []))->toBeFalse();
});

test('deleteTxtByLabel 成功删除 TXT 记录', function () {
    $proxyDNS = Mockery::mock(ProxyDNS::class);
    $proxyDNS->shouldReceive('deleteTXT')
        ->once()
        ->with('proxy.example.com', 'abc123hash');

    $service = new DelegationDnsService;

    $reflection = new ReflectionClass($service);
    $property = $reflection->getProperty('proxyDNS');
    $property->setValue($service, $proxyDNS);

    // deleteTxtByLabel 返回 void，不抛异常即成功
    $service->deleteTxtByLabel('proxy.example.com', 'abc123hash');

    // Mockery 验证 deleteTXT 被调用
    expect(true)->toBeTrue();
});

test('deleteTxtByLabel 参数为空时不调用 DNS', function () {
    $proxyDNS = Mockery::mock(ProxyDNS::class);
    $proxyDNS->shouldNotReceive('deleteTXT');

    $service = new DelegationDnsService;

    $reflection = new ReflectionClass($service);
    $property = $reflection->getProperty('proxyDNS');
    $property->setValue($service, $proxyDNS);

    // proxyZone 为空
    $service->deleteTxtByLabel('', 'label');

    // label 为空
    $service->deleteTxtByLabel('proxy.example.com', '');
});

test('setTxtByLabel DNS 操作失败时返回 false', function () {
    $proxyDNS = Mockery::mock(ProxyDNS::class);
    $proxyDNS->shouldReceive('upsertTXT')
        ->once()
        ->andReturn(false);

    $service = new DelegationDnsService;

    $reflection = new ReflectionClass($service);
    $property = $reflection->getProperty('proxyDNS');
    $property->setValue($service, $proxyDNS);

    $result = $service->setTxtByLabel('proxy.example.com', 'label', ['value']);

    expect($result)->toBeFalse();
});
