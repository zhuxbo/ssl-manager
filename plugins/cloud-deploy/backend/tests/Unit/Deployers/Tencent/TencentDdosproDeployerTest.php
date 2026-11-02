<?php

use Plugins\CloudDeploy\Deployers\Tencent\TencentDdosproDeployer;
use TencentCloud\Antiddos\V20200309\AntiddosClient;
use TencentCloud\Antiddos\V20200309\Models\DescribeL7RulesBySSLCertIdResponse;
use TencentCloud\Antiddos\V20200309\Models\DescribeNewL7RulesResponse;
use TencentCloud\Antiddos\V20200309\Models\ModifyNewDomainRulesResponse;
use Tests\TestCase;

uses(TestCase::class);

function ddosWith(object $client): TencentDdosproDeployer
{
    return new class($client) extends TencentDdosproDeployer
    {
        public function __construct(private object $client) {}

        protected function makeClient(string $kind, array $credentials): object
        {
            return $this->client;
        }
    };
}

test('腾讯高防仅更新指定实例规则，保留回源和 CC 设置', function () {
    $client = Mockery::mock(AntiddosClient::class);
    $response = new DescribeNewL7RulesResponse;
    $response->deserialize(['Rules' => [
        ['Id' => 'other', 'Domain' => 'www.example.com', 'RuleId' => 'wrong'],
        ['Id' => 'ins-1', 'Domain' => 'www.example.com', 'RuleId' => 'right', 'Protocol' => 'https', 'KeepEnable' => 1, 'SourceList' => [['Source' => '1.2.3.4', 'Weight' => 100]], 'CCEnable' => 1],
    ]]);
    $client->shouldReceive('DescribeNewL7Rules')->once()->andReturn($response);
    $client->shouldReceive('ModifyNewDomainRules')->once()->with(Mockery::on(fn ($r) => $r->Id === 'ins-1' && $r->Rule->RuleId === 'right' && $r->Rule->SSLId === 'cert-1' && $r->Rule->CertType === 2 && $r->Rule->SourceList[0]->Source === '1.2.3.4' && $r->Rule->CCEnable === 1))->andReturn(new ModifyNewDomainRulesResponse);
    ddosWith($client)->bind('cert-1', [], ['instance_id' => 'ins-1', 'domain' => 'www.example.com']);
});

test('腾讯高防证书匹配只保留目标实例域名，空集合报错', function () {
    $client = Mockery::mock(AntiddosClient::class);
    $response = new DescribeL7RulesBySSLCertIdResponse;
    $response->deserialize(['CertSet' => [['L7Rules' => [['InsId' => 'other', 'Domain' => 'www.example.com']]]]]);
    $client->shouldReceive('DescribeL7RulesBySSLCertId')->once()->andReturn($response);
    $client->shouldNotReceive('ModifyNewDomainRules');
    expect(fn () => ddosWith($client)->bind('cert-1', [], ['instance_id' => 'ins-1', 'domain_match_pattern' => 'certsan']))->toThrow(RuntimeException::class, '未找到');
});

test('腾讯高防 SDK 可真实构造且 SSL 上传空间复用', function () {
    $deployer = new TencentDdosproDeployer;
    expect($deployer->certUploader()->storeKind())->toBe('tencent_ssl');
    $method = new ReflectionMethod($deployer, 'makeClient');
    expect($method->invoke($deployer, 'antiddos', ['secret_id' => 'test', 'secret_key' => 'test']))->toBeInstanceOf(AntiddosClient::class);
});
