<?php

use App\Services\Delegation\DnsResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
    Http::preventStrayRequests();
    // 即使配置了外部节点，回落接口也只能做本地检测。
    Cache::put('setting:group_name:site', ['dnsTools' => ['https://dns.example.test']], 3600);
});

function localDcvResolver(string $host, string $type, ?array $records): void
{
    $resolver = Mockery::mock(DnsResolver::class);
    $resolver->shouldReceive('queryRecords')->once()->with($host, $type)->andReturn($records);
    app()->instance(DnsResolver::class, $resolver);
}

test('CNAME 本地回落补齐前缀并返回实际目标', function () {
    localDcvResolver('_certum.example.com', 'CNAME', [
        ['name' => '_certum.example.com', 'type' => 'CNAME', 'value' => 'Label.PROXY.test.'],
    ]);
    $response = $this->postJson('/api/dcv/verify', [[
        'domain' => 'example.com', 'method' => 'cname', 'host' => '_certum', 'value' => 'label.proxy.test',
    ]])->assertOk()->assertJsonPath('code', 1);
    expect($response->json('data.results')['example.com'])->toMatchArray([
        'matched' => 'true', 'value' => 'Label.PROXY.test.', 'query' => '_certum.example.com',
    ]);
    Http::assertNothingSent();
});

test('TXT 根域 @ 直接查询且区分大小写', function () {
    localDcvResolver('example.com', 'TXT', [
        ['name' => 'example.com', 'type' => 'TXT', 'value' => 'Token'],
    ]);
    $response = $this->postJson('/api/dcv/verify', [[
        'domain' => 'example.com', 'method' => 'txt', 'host' => '@', 'value' => 'token',
    ]])->assertOk();
    expect($response->json('data.results')['example.com']['matched'])->toBe('false');
});

test('查询保留 TXT 实际 owner 供前端排除 CNAME 链目标记录', function () {
    $records = [['name' => 'label.proxy.test', 'type' => 'TXT', 'value' => 'token']];
    localDcvResolver('_certum.example.com', 'TXT', $records);
    $this->postJson('/api/dns/query', ['domain' => '_certum.example.com', 'type' => 'TXT'])
        ->assertOk()->assertJsonPath('data.records', $records);
    Http::assertNothingSent();
});

test('DNS 无记录与解析服务不可用有不同结果', function (?array $records, string $matched) {
    localDcvResolver('_certum.example.com', 'CNAME', $records);
    $response = $this->postJson('/api/dcv/verify', [[
        'domain' => 'example.com', 'method' => 'cname', 'host' => '_certum', 'value' => 'label.proxy.test',
    ]])->assertOk();
    expect($response->json('data.results')['example.com']['matched'])->toBe($matched);
})->with([[[], 'false'], [null, 'unknown']]);

test('DNS 原始查询失败返回业务错误', function () {
    localDcvResolver('example.com', 'TXT', null);
    $this->postJson('/api/dns/query', ['domain' => 'example.com', 'type' => 'TXT'])
        ->assertOk()->assertJsonPath('code', 0)->assertJsonPath('msg', '本地 DNS 检测服务不可用');
});

test('文件回落拒绝内网或跨域请求', function (string $domain, string $link) {
    $response = $this->postJson('/api/dcv/verify', [[
        'domain' => $domain, 'method' => 'http', 'link' => $link, 'content' => 'token',
    ]])->assertOk();
    expect($response->json('data.results')[$domain]['matched'])->toBe('false');
    Http::assertNothingSent();
})->with([
    ['127.0.0.1', 'http://127.0.0.1/.well-known/pki-validation/test.txt'],
    ['example.com', 'http://127.0.0.1/.well-known/pki-validation/test.txt'],
    ['192.168.1.1', 'http://192.168.1.1/.well-known/pki-validation/test.txt'],
]);

test('文件回落复用本地文件核验并禁用重定向', function () {
    Http::fake(['http://8.8.8.8/*' => Http::response('token', 200)]);
    $response = $this->postJson('/api/dcv/verify', [[
        'domain' => '8.8.8.8', 'method' => 'http', 'link' => 'http://8.8.8.8/.well-known/pki-validation/test.txt', 'content' => 'token',
    ]])->assertOk();
    expect($response->json('data.results')['8.8.8.8']['matched'])->toBe('true');
    Http::assertSent(fn ($request) => $request->hasHeader('Cache-Control', 'no-cache, no-store, max-age=0'));
});

test('公开回落不允许任意文件路径', function () {
    $this->postJson('/api/dcv/verify', [[
        'domain' => 'example.com', 'method' => 'http', 'link' => 'http://example.com/admin', 'content' => 'token',
    ]])->assertOk()->assertJsonPath('code', 0);
    Http::assertNothingSent();
});

test('拒绝非法 DNS 查询类型', function () {
    $response = $this->postJson('/api/dns/query', ['domain' => 'example.com', 'type' => 'ANY']);
    expect($response->json('code'))->toBe(0);
    Http::assertNothingSent();
});

test('公开回落拒绝批量工作放大', function () {
    $item = ['domain' => 'example.com', 'method' => 'cname', 'host' => '_certum', 'value' => 'proxy.test'];
    $this->postJson('/api/dcv/verify', [$item, $item])->assertJsonPath('code', 0);
    Http::assertNothingSent();
});

test('回落使用独立 IP 限额且不读取业务 token', function () {
    $resolver = Mockery::mock(DnsResolver::class);
    $resolver->shouldReceive('queryRecords')->times(120)->andReturn([]);
    app()->instance(DnsResolver::class, $resolver);
    for ($i = 0; $i < 120; $i++) {
        $this->postJson('/api/dns/query', ['domain' => 'example.com', 'type' => 'TXT'])
            ->assertOk()->assertJsonPath('code', 1);
    }
    $this->postJson('/api/dns/query', ['domain' => 'example.com', 'type' => 'TXT'])->assertStatus(429);
});

test('本地回落按项目 IDNA 口径解析国际化域名', function () {
    localDcvResolver('_certum.xn--fsqu00a.com', 'CNAME', []);
    $response = $this->postJson('/api/dcv/verify', [[
        'domain' => '例子.com', 'method' => 'cname', 'host' => '_certum', 'value' => 'proxy.test',
    ]])->assertOk();
    expect($response->json('data.results')['例子.com']['query'])->toBe('_certum.xn--fsqu00a.com');
});
