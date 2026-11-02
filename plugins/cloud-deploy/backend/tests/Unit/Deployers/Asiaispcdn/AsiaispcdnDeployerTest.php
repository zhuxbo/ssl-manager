<?php

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Plugins\CloudDeploy\Deployers\Asiaispcdn\AsiaispcdnClient;
use Plugins\CloudDeploy\Deployers\Asiaispcdn\AsiaispcdnDeployer;
use Tests\TestCase;

uses(TestCase::class);

function asiaWith(object $client): AsiaispcdnDeployer
{
    return new class($client) extends AsiaispcdnDeployer
    {
        public function __construct(private object $client) {}

        protected function makeClient(string $kind, array $credentials): object
        {
            return $this->client;
        }
    };
}

test('AsiaISP 上传完整链并返回证书 ID', function () {
    $client = Mockery::mock(AsiaispcdnClient::class);
    $client->shouldReceive('call')->once()->with('POST', 'certificateUpload', Mockery::on(fn ($p) => $p['publicKey'] === "CERT\nCHAIN" && $p['privateKey'] === 'KEY'))->andReturn(42);
    expect(asiaWith($client)->certUploader()->upload('CERT', 'KEY', 'CHAIN', []))->toBe('42');
});

test('AsiaISP 泛域名只更新有效匹配域名且跳过已绑定证书', function () {
    $client = Mockery::mock(AsiaispcdnClient::class);
    $client->shouldReceive('call')->with('GET', 'domainQueryList', [])->once()->andReturn([
        ['domain' => 'www.example.com', 'domainStatus' => 1, 'certId' => 1],
        ['domain' => 'old.example.com', 'domainStatus' => 0, 'certId' => 1],
        ['domain' => 'deep.www.example.com', 'domainStatus' => 1, 'certId' => 1],
        ['domain' => 'done.example.com', 'domainStatus' => 1, 'certId' => 42],
    ]);
    $client->shouldReceive('call')->with('PUT', 'domainModify', ['domain' => 'www.example.com', 'protocol' => 'https', 'certId' => 42])->once()->andReturn(1);
    asiaWith($client)->bind('42', [], ['domain' => '*.example.com', 'domain_match_pattern' => 'wildcard']);
});

test('AsiaISP 操作中域名重试且不修改配置', function () {
    $client = Mockery::mock(AsiaispcdnClient::class);
    $client->shouldReceive('call')->with('GET', 'domainQueryList', ['domain' => 'example.com'])->once()->andReturn([
        ['domain' => 'example.com', 'domainStatus' => 2, 'certId' => 1],
    ]);
    expect(fn () => asiaWith($client)->bind('42', [], ['domain' => 'example.com']))->toThrow(RuntimeException::class, '正在操作');
});

test('AsiaISP HTTP 签名覆盖实际 query 和 body，使用无填充 URL-safe HMAC SHA1', function () {
    $http = Mockery::mock(ClientInterface::class);
    $http->shouldReceive('request')->once()->with('PUT', 'https://api.asia-isp.com/openapi/v3/stat?action=domainModify', Mockery::on(function ($options) {
        $h = $options['headers'];
        $canonical = 'accessKeySecret=SK&body='.$options['body'].'&method=PUT&nonce='.$h['nonce'].'&queryString=action=domainModify&timestamp='.$h['timestamp'].'&uri=/openapi/v3/stat';
        expect($h['signature'])->toBe(rtrim(strtr(base64_encode(hash_hmac('sha1', $canonical, 'SK', true)), '+/', '-_'), '='));
        expect(json_decode($options['body'], true))->toBe(['domain' => 'example.com', 'certId' => 42]);

        return $h['accessKeyId'] === 'AK';
    }))->andReturn(new Response(200, [], '{"code":0,"data":1}'));
    expect((new AsiaispcdnClient($http, 'AK', 'SK'))->call('PUT', 'domainModify', ['domain' => 'example.com', 'certId' => 42]))->toBe(1);
});

test('AsiaISP 异常不暴露云端回显凭证', function () {
    $client = Mockery::mock(AsiaispcdnClient::class);
    $client->shouldReceive('call')->andThrow(new RuntimeException('SECRET PRIVATE KEY'));
    try {
        asiaWith($client)->bind('42', [], ['domain' => 'example.com']);
        $this->fail('应抛异常');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->not->toContain('SECRET')->not->toContain('PRIVATE KEY');
        expect($e->getPrevious())->toBeNull();
    }
});
