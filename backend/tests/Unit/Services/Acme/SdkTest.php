<?php

use App\Services\Acme\Api\certum\Sdk;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

beforeEach(function () {
    config([
        'acme.api.base_url' => 'https://gateway.test',
        'acme.api.api_key' => 'test-api-key-123',
    ]);
});

test('new sends POST to gateway', function () {
    Http::fake([
        'gateway.test/api/acme/new' => Http::response(['code' => 1, 'data' => ['id' => 1]], 200),
    ]);

    $sdk = new Sdk;
    $result = $sdk->new(['customer' => 'test@example.com', 'product_code' => '12345']);

    expect($result['code'])->toBe(1);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://gateway.test/api/acme/new'
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer test-api-key-123')
            && $request->data()['customer'] === 'test@example.com';
    });
});

test('get sends GET to gateway', function () {
    Http::fake([
        'gateway.test/api/acme/get*' => Http::response(['code' => 1, 'data' => ['id' => 42]], 200),
    ]);

    $sdk = new Sdk;
    $result = $sdk->get(42);

    expect($result['code'])->toBe(1);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'https://gateway.test/api/acme/get')
            && $request->method() === 'GET'
            && $request['order_id'] == 42;
    });
});

test('cancel sends POST to gateway', function () {
    Http::fake([
        'gateway.test/api/acme/cancel' => Http::response(['code' => 1, 'data' => []], 200),
    ]);

    $sdk = new Sdk;
    $result = $sdk->cancel(42);

    expect($result['code'])->toBe(1);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://gateway.test/api/acme/cancel'
            && $request->method() === 'POST'
            && $request->data()['order_id'] == 42;
    });
});

test('sync sends POST to gateway', function () {
    Http::fake([
        'gateway.test/api/acme/sync' => Http::response(['code' => 1, 'data' => []], 200),
    ]);

    $sdk = new Sdk;
    $result = $sdk->sync(42);

    expect($result['code'])->toBe(1);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://gateway.test/api/acme/sync'
            && $request->method() === 'POST'
            && $request->data()['order_id'] == 42;
    });
});

test('returns error when not configured', function () {
    config([
        'acme.api.base_url' => '',
        'acme.api.api_key' => '',
    ]);

    $sdk = new Sdk;
    $result = $sdk->get(1);

    expect($result['code'])->toBe(0);
    expect($result['msg'])->toContain('未配置');
});

test('returns error on connection failure', function () {
    Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
    });

    $sdk = new Sdk;
    $result = $sdk->get(1);

    expect($result['code'])->toBe(0);
    expect($result['msg'])->toContain('连接失败');
});

test('returns error when response has no code', function () {
    Http::fake([
        'gateway.test/api/acme/get*' => Http::response(['data' => []], 200),
    ]);

    $sdk = new Sdk;
    $result = $sdk->get(1);

    expect($result['code'])->toBe(0);
    expect($result['msg'])->toContain('格式错误');
});

test('returns error on http failure', function () {
    Http::fake([
        'gateway.test/api/acme/get*' => Http::response(['msg' => '服务器错误'], 500),
    ]);

    $sdk = new Sdk;
    $result = $sdk->get(1);

    expect($result['code'])->toBe(0);
});
