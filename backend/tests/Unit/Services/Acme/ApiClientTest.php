<?php

use App\Services\Acme\ApiClient;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

/**
 * 模拟 system_settings
 */
function mockSystemSettings(object $app, string $url, string $key): void
{
    // 因为 ApiClient 构造函数中直接调用 get_system_setting，
    // 我们需要在容器中重新绑定
    $app->bind(ApiClient::class, function () use ($url, $key) {
        $client = new ApiClient;

        // 使用反射设置私有属性
        $ref = new \ReflectionClass($client);

        $baseUrlProp = $ref->getProperty('baseUrl');
        $baseUrlProp->setAccessible(true);
        $baseUrlProp->setValue($client, rtrim($url, '/'));

        $apiKeyProp = $ref->getProperty('apiKey');
        $apiKeyProp->setAccessible(true);
        $apiKeyProp->setValue($client, $key);

        return $client;
    });
}

test('request uses system setting url and key', function () {
    mockSystemSettings($this->app, 'https://gateway.test/api', 'test-api-key-123');

    Http::fake([
        'gateway.test/api/*' => Http::response(['code' => 1, 'data' => ['id' => 1]], 200),
    ]);

    $client = app(ApiClient::class);
    $result = $client->getOrder(1);

    expect($result['code'])->toBe(1);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'gateway.test/api')
            && $request->hasHeader('Authorization', 'Bearer test-api-key-123');
    });
});

test('create order sends correct payload', function () {
    mockSystemSettings($this->app, 'https://gateway.test/api', 'test-key');

    Http::fake([
        'gateway.test/api/*' => Http::response(['code' => 1, 'data' => ['id' => 99]], 200),
    ]);

    $client = app(ApiClient::class);
    $result = $client->createOrder('test@example.com', 'DV_SSL', ['example.com', '*.example.com'], 'ref123');

    expect($result['code'])->toBe(1);

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://gateway.test/api/orders'
            && $body['customer'] === 'test@example.com'
            && $body['product_code'] === 'DV_SSL'
            && $body['domains'] === ['example.com', '*.example.com']
            && $body['refer_id'] === 'ref123';
    });
});

test('reissue order sends correct payload', function () {
    mockSystemSettings($this->app, 'https://gateway.test/api', 'test-key');

    Http::fake([
        'gateway.test/api/*' => Http::response(['code' => 1, 'data' => ['id' => 99]], 200),
    ]);

    $client = app(ApiClient::class);
    $result = $client->reissueOrder(42, ['new.example.com'], 'ref456');

    expect($result['code'])->toBe(1);

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://gateway.test/api/orders/reissue/42'
            && $body['domains'] === ['new.example.com']
            && $body['refer_id'] === 'ref456';
    });
});

test('respond to challenge sends correct endpoint', function () {
    mockSystemSettings($this->app, 'https://gateway.test/api', 'test-key');

    Http::fake([
        'gateway.test/api/*' => Http::response(['code' => 1, 'data' => ['status' => 'valid']], 200),
    ]);

    $client = app(ApiClient::class);
    $result = $client->respondToChallenge(42);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://gateway.test/api/challenges/respond/42';
    });
});

test('finalize order sends csr', function () {
    mockSystemSettings($this->app, 'https://gateway.test/api', 'test-key');

    Http::fake([
        'gateway.test/api/*' => Http::response(['code' => 1, 'data' => ['status' => 'valid']], 200),
    ]);

    $client = app(ApiClient::class);
    $result = $client->finalizeOrder(10, 'test-csr-pem');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://gateway.test/api/orders/finalize/10'
            && $request->data()['csr'] === 'test-csr-pem';
    });
});

test('get order authorizations sends correct endpoint', function () {
    mockSystemSettings($this->app, 'https://gateway.test/api', 'test-key');

    Http::fake([
        'gateway.test/api/*' => Http::response(['code' => 1, 'data' => []], 200),
    ]);

    $client = app(ApiClient::class);
    $client->getOrderAuthorizations(15);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://gateway.test/api/orders/authorizations/15';
    });
});

test('get certificate sends correct endpoint', function () {
    mockSystemSettings($this->app, 'https://gateway.test/api', 'test-key');

    Http::fake([
        'gateway.test/api/*' => Http::response(['code' => 1, 'data' => []], 200),
    ]);

    $client = app(ApiClient::class);
    $client->getCertificate(20);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://gateway.test/api/orders/certificate/20';
    });
});

test('is configured returns false when not set', function () {
    mockSystemSettings($this->app, '', '');

    $client = app(ApiClient::class);
    expect($client->isConfigured())->toBeFalse();
});

test('is configured returns true when set', function () {
    mockSystemSettings($this->app, 'https://gateway.test/api', 'key');

    $client = app(ApiClient::class);
    expect($client->isConfigured())->toBeTrue();
});

test('request returns error when not configured', function () {
    mockSystemSettings($this->app, '', '');

    $client = app(ApiClient::class);
    $result = $client->getOrder(1);

    expect($result['code'])->toBe(0);
    expect($result['msg'])->toContain('not configured');
});

test('revoke certificate sends serial number', function () {
    mockSystemSettings($this->app, 'https://gateway.test/api', 'test-key');

    Http::fake([
        'gateway.test/api/*' => Http::response(['code' => 1], 200),
    ]);

    $client = app(ApiClient::class);
    $result = $client->revokeCertificate('ABCD1234');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://gateway.test/api/certificates/revoke'
            && $request->data()['serial_number'] === 'ABCD1234'
            && $request->data()['reason'] === 'UNSPECIFIED';
    });
});
