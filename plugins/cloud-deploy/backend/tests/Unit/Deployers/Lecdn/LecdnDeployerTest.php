<?php

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Plugins\CloudDeploy\Deployers\Lecdn\LecdnApiException;
use Plugins\CloudDeploy\Deployers\Lecdn\LecdnDeployer;
use Plugins\CloudDeploy\Deployers\Lecdn\LecdnRestClient;
use Tests\TestCase;

uses(TestCase::class);

/** 测试子类：override makeClient（api kind）注入 mock LecdnRestClient。 */
function lecdnDeployerWith(callable $clientFactory): LecdnDeployer
{
    return new class($clientFactory) extends LecdnDeployer
    {
        public function __construct(private $factory) {}

        protected function makeClient(string $kind, array $credentials): object
        {
            return ($this->factory)($kind, $credentials);
        }
    };
}

function lecdnCertRef(): array
{
    return ['cert' => 'CERTPEM', 'key' => 'KEYPEM', 'chain' => 'CHAINPEM'];
}

function lecdnClientCreds(): array
{
    return ['server_url' => 'https://lecdn:8080', 'api_version' => 'v3', 'api_role' => 'client', 'username' => 'u', 'password' => 'p'];
}

function lecdnMasterCreds(): array
{
    return ['server_url' => 'https://lecdn:8080', 'api_version' => 'v3', 'api_role' => 'master', 'username' => 'u', 'password' => 'p'];
}

test('LeCDN 为内联型（usesRemoteCertStore=false）+ 元信息', function () {
    $deployer = new LecdnDeployer;
    expect($deployer->provider())->toBe('lecdn');
    expect($deployer->product())->toBe('lecdn');
    expect($deployer->usesRemoteCertStore())->toBeFalse();
    expect($deployer->certUploader())->toBeNull();
    expect(array_column($deployer->configSchema(), 'key'))->toContain('certificate_id')->toContain('client_id');
});

test('bind 调 updateCertificate（id 转 int、完整链→ssl_pem、key→ssl_key、client_id 透传）', function () {
    $captured = null;
    $client = Mockery::mock(LecdnRestClient::class);
    $client->shouldReceive('updateCertificate')->once()
        ->andReturnUsing(function (int $id, string $cert, string $key, int $clientId) use (&$captured) {
            $captured = compact('id', 'cert', 'key', 'clientId');
        });

    $deployer = lecdnDeployerWith(fn () => $client);
    $deployer->bind(lecdnCertRef(), lecdnMasterCreds(), ['certificate_id' => '9', 'client_id' => '3']);

    expect($captured['id'])->toBe(9);
    expect($captured['cert'])->toContain('CERTPEM')->toContain('CHAINPEM');
    expect($captured['key'])->toBe('KEYPEM');
    expect($captured['clientId'])->toBe(3);
});

test('未填 client_id 时 client_id 传 0', function () {
    $captured = null;
    $client = Mockery::mock(LecdnRestClient::class);
    $client->shouldReceive('updateCertificate')->once()
        ->andReturnUsing(function (int $id, string $cert, string $key, int $clientId) use (&$captured) {
            $captured = $clientId;
        });

    $deployer = lecdnDeployerWith(fn () => $client);
    $deployer->bind(lecdnCertRef(), lecdnClientCreds(), ['certificate_id' => 1]);

    expect($captured)->toBe(0);
});

test('缺 certificate_id 抛业务错误', function () {
    $deployer = lecdnDeployerWith(fn () => new stdClass);
    expect(fn () => $deployer->bind(lecdnCertRef(), lecdnClientCreds(), []))
        ->toThrow(RuntimeException::class, '缺少配置 certificate_id');
});

test('非法 api_role 抛业务错误（清晰文案，未被脱敏）', function () {
    $deployer = lecdnDeployerWith(fn () => new stdClass);
    $creds = lecdnClientCreds();
    $creds['api_role'] = 'bogus';
    expect(fn () => $deployer->bind(lecdnCertRef(), $creds, ['certificate_id' => 1]))
        ->toThrow(RuntimeException::class, 'client/master');
});

test('非 v3 api_version 抛业务错误', function () {
    $deployer = lecdnDeployerWith(fn () => new stdClass);
    $creds = lecdnClientCreds();
    $creds['api_version'] = 'v2';
    expect(fn () => $deployer->bind(lecdnCertRef(), $creds, ['certificate_id' => 1]))
        ->toThrow(RuntimeException::class, 'v3');
});

test('bind 遇 LecdnApiException 时脱敏重抛（含错误码、无凭证、不挂 previous）', function () {
    $client = Mockery::mock(LecdnRestClient::class);
    $client->shouldReceive('updateCertificate')->andThrow(new LecdnApiException('404', 'certificate not found'));

    $deployer = lecdnDeployerWith(fn () => $client);
    try {
        $deployer->bind(lecdnCertRef(), [
            'server_url' => 'https://lecdn:8080', 'api_version' => 'v3', 'api_role' => 'client', 'username' => 'u', 'password' => 'PASS-LEAK-123',
        ], ['certificate_id' => 1]);
        expect(false)->toBeTrue('应抛异常');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toContain('404')->toContain('certificate not found');
        expect($e->getMessage())->not->toContain('PASS-LEAK-123');
        expect($e->getPrevious())->toBeNull();
        expect($e->getTraceAsString())->not->toContain('PASS-LEAK-123');
    }
});

// ============ RestClient 角色分支（登录 body + 更新 body 形态）============

/** 用真实 LecdnRestClient + mock Guzzle 捕获请求，验证角色分支线协议。 */
function lecdnCaptureRequests(string $role): array
{
    $requests = [];
    $http = Mockery::mock(ClientInterface::class);
    $http->shouldReceive('request')->andReturnUsing(function (string $method, string $path, array $opts) use (&$requests) {
        $requests[] = ['method' => $method, 'path' => $path, 'json' => $opts['json'] ?? null, 'headers' => $opts['headers'] ?? []];
        // 登录请求返回 token；其余返回 code=200
        if ($path === 'auth/login') {
            return new Response(200, [], json_encode(['code' => 200, 'data' => ['token' => 'TKN']]));
        }

        return new Response(200, [], json_encode(['code' => 200]));
    });

    $client = new LecdnRestClient($http, $role, 'theuser', 'thepass');
    $client->updateCertificate(7, 'CERT', 'KEY', 42);

    return $requests;
}

test('client 角色：登录 body 含 email+username+password，更新 body 无 client_id，Bearer 头', function () {
    $reqs = lecdnCaptureRequests(LecdnRestClient::ROLE_CLIENT);

    // 登录
    $login = $reqs[0];
    expect($login['path'])->toBe('auth/login');
    expect($login['json'])->toBe(['email' => 'theuser', 'username' => 'theuser', 'password' => 'thepass']);

    // 更新（PUT /certificate/7），无 client_id，带 Bearer token
    $update = $reqs[1];
    expect($update['method'])->toBe('PUT');
    expect($update['path'])->toBe('certificate/7');
    expect($update['json'])->not->toHaveKey('client_id');
    expect($update['json']['type'])->toBe('upload');
    expect($update['json']['ssl_pem'])->toBe(base64_encode('CERT')); // 对齐新版接口编码
    expect($update['json']['ssl_key'])->toBe(base64_encode('KEY'));
    expect($update['json']['auto_renewal'])->toBeFalse();
    expect($update['headers']['Authorization'])->toBe('Bearer TKN');
});

test('master 角色：登录 body 仅 username+password，更新 body 含 client_id', function () {
    $reqs = lecdnCaptureRequests(LecdnRestClient::ROLE_MASTER);

    $login = $reqs[0];
    expect($login['json'])->toBe(['username' => 'theuser', 'password' => 'thepass']);
    expect($login['json'])->not->toHaveKey('email');

    $update = $reqs[1];
    expect($update['json']['client_id'])->toBe(42);
    expect($update['json']['ssl_pem'])->toBe(base64_encode('CERT'));
});

test('token 缓存：多次调用仅登录一次（无过期重登）', function () {
    $loginCount = 0;
    $http = Mockery::mock(ClientInterface::class);
    $http->shouldReceive('request')->andReturnUsing(function (string $method, string $path, array $opts) use (&$loginCount) {
        if ($path === 'auth/login') {
            $loginCount++;

            return new Response(200, [], json_encode(['code' => 200, 'data' => ['token' => 'TKN']]));
        }

        return new Response(200, [], json_encode(['code' => 200]));
    });

    $client = new LecdnRestClient($http, LecdnRestClient::ROLE_CLIENT, 'u', 'p');
    $client->updateCertificate(1, 'C', 'K');
    $client->updateCertificate(2, 'C', 'K');

    expect($loginCount)->toBe(1);
});

test('RestClient code≠200 归一为 LecdnApiException（读 msg/message）', function () {
    $http = Mockery::mock(ClientInterface::class);
    $http->shouldReceive('request')->andReturnUsing(function (string $method, string $path) {
        if ($path === 'auth/login') {
            return new Response(200, [], json_encode(['code' => 200, 'data' => ['token' => 'TKN']]));
        }

        return new Response(200, [], json_encode(['code' => 500, 'msg' => 'boom']));
    });

    $client = new LecdnRestClient($http, LecdnRestClient::ROLE_CLIENT, 'u', 'p');
    expect(fn () => $client->updateCertificate(1, 'C', 'K'))
        ->toThrow(LecdnApiException::class, 'boom');
});

test('LeCDN v4 API Key 直接鉴权，不登录且不发送 v3 专用字段', function (string $role) {
    $http = Mockery::mock(ClientInterface::class);
    $http->shouldReceive('request')->once()->with('PUT', 'certificate/7', Mockery::on(function ($opts) {
        expect($opts['headers']['Authorization'])->toBe('APIKEY');
        expect($opts['json'])->not->toHaveKeys(['type', 'client_id']);
        expect(base64_decode($opts['json']['ssl_pem']))->toBe('CERT');
        expect(base64_decode($opts['json']['ssl_key']))->toBe('KEY');

        return true;
    }))->andReturn(new Response(200, [], '{"code":200}'));
    (new LecdnRestClient($http, $role, '', '', 'v4', 'APIKEY'))->updateCertificate(7, 'CERT', 'KEY', 9);
})->with(['client', 'master']);

test('LeCDN v4 用户端登录路径为 login，主控端仍为 auth/login', function (string $role, string $path) {
    $http = Mockery::mock(ClientInterface::class);
    $http->shouldReceive('request')->with('POST', $path, Mockery::type('array'))->once()->andReturn(new Response(200, [], '{"code":200,"data":{"token":"TOKEN"}}'));
    $http->shouldReceive('request')->with('PUT', 'certificate/7', Mockery::on(fn ($o) => $o['headers']['Authorization'] === 'Bearer TOKEN'))->once()->andReturn(new Response(200, [], '{"code":200}'));
    (new LecdnRestClient($http, $role, 'u', 'p', 'v4'))->updateCertificate(7, 'CERT', 'KEY');
})->with([['client', 'login'], ['master', 'auth/login']]);
