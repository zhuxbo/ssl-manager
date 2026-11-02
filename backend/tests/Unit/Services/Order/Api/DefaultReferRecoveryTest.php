<?php

use App\Services\LogBuffer;
use App\Services\Order\Api\default\Api;
use App\Services\Order\Api\default\Sdk;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(TestCase::class);

class ReferRecoverySdk extends Sdk
{
    public array $history = [];

    public array $configs = [];

    private HandlerStack $handler;

    public function __construct(array $responses)
    {
        $this->handler = HandlerStack::create(new MockHandler(array_map(
            fn (array $response) => new Response(200, [], json_encode($response)), $responses
        )));
        $this->handler->push(Middleware::history($this->history));
    }

    protected function makeClient(array $config = []): Client
    {
        $this->configs[] = $config;

        return new Client(['handler' => $this->handler] + $config);
    }
}

function referRecoveryApi(ReferRecoverySdk $sdk): Api
{
    $api = new Api;
    (new ReflectionProperty(Api::class, 'sdk'))->setValue($api, $sdk);

    return $api;
}

function referRecoveryParams(): array
{
    return [
        'refer_id' => 'stable-refer', 'product_api_id' => 'test-product',
        'period' => 12, 'plus' => 0, 'csr' => 'original-csr',
        'alternative_names' => 'example.com', 'dcv' => ['method' => 'dns'],
        'contact' => [], 'last_api_id' => 'previous-order',
    ];
}

beforeEach(function () {
    Cache::put('setting:group_name:ca', ['url' => 'https://upstream.test/api/v2', 'token' => 'tok'], 60);
    LogBuffer::clear();
});
afterEach(fn () => LogBuffer::clear());

test('已有申请只查询并认领，不再次提交', function (string $action, string $status) {
    $sdk = new ReferRecoverySdk([
        ['code' => 1, 'data' => ['order_id' => 'existing-order']],
        ['code' => 1, 'data' => ['status' => $status, 'csr' => 'original-csr', 'cert_apply_status' => 2, 'dcv' => null, 'validation' => null]],
    ]);
    $result = referRecoveryApi($sdk)->$action(referRecoveryParams());

    expect($result['code'])->toBe(1)
        ->and($result['data']['api_id'])->toBe('existing-order')
        ->and($result['recovered'])->toBeTrue()
        ->and(array_map(fn ($h) => $h['request']->getMethod(), $sdk->history))->toBe(['GET', 'GET'])
        ->and($sdk->history[0]['request']->getUri()->getQuery())->toBe('refer_id=stable-refer')
        ->and($sdk->history[1]['request']->getUri()->getQuery())->toBe('order_id=existing-order')
        ->and(array_sum(array_column($sdk->configs, 'timeout')))->toBeLessThanOrEqual(45);
})->with(['new', 'renew', 'reissue'])->with(['processing', 'approving', 'active']);

test('明确不存在或占位未提交才使用原参数提交', function (string $action, string $message) {
    $sdk = new ReferRecoverySdk([
        ['code' => 0, 'msg' => $message],
        ['code' => 1, 'data' => ['order_id' => 'submitted', 'dcv' => null, 'validation' => null]],
    ]);
    $result = referRecoveryApi($sdk)->$action(referRecoveryParams());
    parse_str((string) $sdk->history[1]['request']->getBody(), $body);

    expect($result['data']['api_id'])->toBe('submitted')
        ->and($sdk->history[1]['request']->getMethod())->toBe('POST')
        ->and($sdk->history[1]['request']->getUri()->getPath())->toBe('/api/v2/'.$action)
        ->and($body['refer_id'])->toBe('stable-refer')
        ->and($body['csr'])->toBe('original-csr')
        ->and(array_sum(array_column($sdk->configs, 'timeout')))->toBeLessThanOrEqual(45);
})->with(['new', 'renew', 'reissue'])->with(['Refer id not found', '订单处理中，请稍后重试']);

test('反查失败或异常响应不提交', function (array $lookup) {
    $sdk = new ReferRecoverySdk([$lookup]);
    $result = referRecoveryApi($sdk)->reissue(referRecoveryParams());

    expect($result['code'])->toBe(0)->and($sdk->history)->toHaveCount(1);
})->with([
    [['code' => 0, 'msg' => '上游连接超时，请稍后重试']],
    [['code' => 0, 'msg' => 'Unauthorized']],
    [['code' => 0, 'msg' => '重签提交结果待核查，请联系管理员']],
    [['code' => 1, 'data' => []]],
]);

test('已找到订单但读取失败或终态时不认领也不提交', function (array $details) {
    $sdk = new ReferRecoverySdk([
        ['code' => 1, 'data' => ['order_id' => 'existing-order']], $details,
    ]);
    $result = referRecoveryApi($sdk)->reissue(referRecoveryParams());

    expect($result['code'])->toBe(0)->and($sdk->history)->toHaveCount(2);
})->with([
    [['code' => 0, 'msg' => 'CA 内部错误，请联系管理员']],
    [['code' => 1, 'data' => ['status' => 'cancelled']]],
    [['code' => 1, 'data' => ['status' => 'revoked']]],
    [['code' => 1, 'data' => ['status' => 'active', 'csr' => 'different-csr']]],
    [['code' => 1, 'data' => ['status' => 'active']]],
    [['code' => 1, 'data' => []]],
]);

test('无 DCV 产品认领也保留同步标记', function (string $type) {
    $sdk = new ReferRecoverySdk([
        ['code' => 1, 'data' => ['order_id' => 'existing-order']],
        ['code' => 1, 'data' => ['status' => 'approving', 'csr' => 'original-csr']],
    ]);
    $params = referRecoveryParams() + ['product_type' => $type, 'email' => 'test@example.com'];
    $result = referRecoveryApi($sdk)->new($params);
    expect($result['recovered'])->toBeTrue()->and($result['data']['api_id'])->toBe('existing-order');
})->with(['smime', 'codesign', 'docsign']);

test('空 refer_id 不发请求', function () {
    $sdk = new ReferRecoverySdk([]);
    $params = referRecoveryParams();
    $params['refer_id'] = '';
    expect(referRecoveryApi($sdk)->reissue($params)['code'])->toBe(0)
        ->and($sdk->history)->toBeEmpty();
});
