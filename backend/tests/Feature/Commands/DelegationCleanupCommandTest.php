<?php

use App\Models\Cert;
use App\Models\CnameDelegation;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Delegation\DelegationDnsService;
use App\Services\Delegation\ProxyDNS;

beforeEach(function () {
    $this->proxyDNS = Mockery::mock(ProxyDNS::class);
    $this->app->instance(ProxyDNS::class, $this->proxyDNS);
});

test('签名为 delegation:cleanup', function () {
    // 代理域名未配置时直接返回
    $this->artisan('delegation:cleanup')
        ->expectsOutputToContain('代理域名未设置')
        ->assertSuccessful();
});

test('代理域名未配置时终止执行', function () {
    $this->artisan('delegation:cleanup')
        ->expectsOutputToContain('代理域名未设置')
        ->assertSuccessful();
});

test('没有需要清理的记录时正常退出', function () {
    // 清除设置缓存
    \Illuminate\Support\Facades\Cache::flush();

    // 设置系统配置
    $group = \App\Models\SettingGroup::factory()->create(['name' => 'site']);
    \App\Models\Setting::factory()->create([
        'group_id' => $group->id,
        'key' => 'delegation',
        'type' => 'array',
        'value' => ['proxyZone' => 'proxy.example.com'],
    ]);

    $this->proxyDNS->shouldReceive('getAllTxtRecords')
        ->with('proxy.example.com')
        ->andReturn([]);

    $this->artisan('delegation:cleanup')
        ->expectsOutputToContain('没有需要清理的记录')
        ->assertSuccessful();
});
