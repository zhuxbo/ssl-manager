<?php

use AlibabaCloud\SDK\Mse\V20190531\Models\ListGatewayDomainResponse;
use AlibabaCloud\SDK\Mse\V20190531\Models\UpdateGatewayDomainResponse;
use AlibabaCloud\SDK\Mse\V20190531\Models\UpdateSSLCertResponse;
use AlibabaCloud\SDK\Mse\V20190531\Mse;
use Plugins\CloudDeploy\Deployers\Aliyun\AliyunMseDeployer;
use Plugins\CloudDeploy\Support\OutboundDestinationPolicy;
use Tests\TestCase;

uses(TestCase::class);

function mseWith(object $client): AliyunMseDeployer
{
    return new class($client) extends AliyunMseDeployer
    {
        public function __construct(private object $client) {}

        protected function makeClient(string $kind, array $credentials): object
        {
            return $this->client;
        }
    };
}

test('MSE 匹配网关域名并按已有协议更新，跳过删除中的域名', function (string $protocol) {
    $client = Mockery::mock(Mse::class);
    $client->shouldReceive('listGatewayDomain')->once()->with(Mockery::on(fn ($r) => $r->gatewayUniqueId === 'gw-1'))->andReturn(ListGatewayDomainResponse::fromMap(['body' => ['Success' => true, 'Data' => [
        ['Id' => 1, 'Name' => 'www.example.com', 'Protocol' => $protocol, 'Status' => 1, 'TlsMin' => 'TLS 1.1'],
        ['Id' => 2, 'Name' => 'deleted.example.com', 'Status' => 5],
        ['Id' => 3, 'Name' => 'deep.www.example.com', 'Status' => 1],
    ]]]));
    if ($protocol === 'HTTPS') {
        $client->shouldReceive('updateSSLCert')->once()->with(Mockery::on(fn ($r) => $r->domainId === 1 && $r->certIdentifier === '123-cn-hangzhou'))->andReturn(UpdateSSLCertResponse::fromMap(['body' => ['Success' => true]]));
        $client->shouldNotReceive('updateGatewayDomain');
    } else {
        $client->shouldReceive('updateGatewayDomain')->once()->with(Mockery::on(fn ($r) => $r->id === 1 && $r->protocol === 'HTTPS' && $r->tlsMin === 'TLS 1.1' && $r->tlsMax === 'TLS 1.3'))->andReturn(UpdateGatewayDomainResponse::fromMap(['body' => ['Success' => true]]));
        $client->shouldNotReceive('updateSSLCert');
    }
    mseWith($client)->bind('123-cn-hangzhou', [], ['region' => 'cn-hangzhou', 'gateway_id' => 'gw-1', 'domain_match_pattern' => 'wildcard', 'domain' => '*.example.com']);
})->with(['HTTP', 'HTTPS']);

test('MSE 接口 Success=false 不得报告成功且异常不泄露请求', function () {
    $client = Mockery::mock(Mse::class);
    $client->shouldReceive('listGatewayDomain')->andReturn(ListGatewayDomainResponse::fromMap(['body' => ['Success' => false, 'Message' => 'SECRET']]));
    expect(fn () => mseWith($client)->bind('123', [], ['region' => 'cn-hangzhou', 'gateway_id' => 'gw', 'domain' => 'example.com']))->toThrow(RuntimeException::class);
});

test('MSE 无匹配域名不执行修改', function () {
    $client = Mockery::mock(Mse::class);
    $client->shouldReceive('listGatewayDomain')->andReturn(ListGatewayDomainResponse::fromMap(['body' => ['Success' => true, 'Data' => []]]));
    $client->shouldNotReceive('updateSSLCert');
    expect(fn () => mseWith($client)->bind('123', [], ['region' => 'cn-hangzhou', 'gateway_id' => 'gw', 'domain' => 'example.com']))->toThrow(RuntimeException::class, '未找到');
});

test('MSE CAS 上传按国内与国际地域路由，SDK 可真实构造', function (string $region, string $storeKind) {
    app()->instance(OutboundDestinationPolicy::class, new OutboundDestinationPolicy(fn () => ['8.8.8.8'], []));
    $deployer = new AliyunMseDeployer;
    expect($deployer->certUploader(['region' => $region])->storeKind())->toBe($storeKind);
    $method = new ReflectionMethod($deployer, 'makeClient');
    expect($method->invoke($deployer, 'mse', ['region' => $region, 'access_key_id' => 'test', 'access_key_secret' => 'test']))->toBeInstanceOf(Mse::class);
})->with([['cn-beijing', 'cas'], ['eu-central-1', 'cas:ap-southeast-1']]);
