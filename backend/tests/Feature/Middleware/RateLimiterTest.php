<?php

use App\Http\Middleware\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

test('RateLimiter 正常请求透传', function () {
    $middleware = new RateLimiter;
    $request = Request::create('/api/v2/products', 'GET');

    $response = $middleware->handle($request, function () {
        return new Response('ok');
    }, 'v2');

    expect($response->getContent())->toBe('ok');
});

test('RateLimiter IP 限流 - 正常范围内通过', function () {
    $middleware = new RateLimiter;

    for ($i = 0; $i < 10; $i++) {
        $request = Request::create('/api/v2/products', 'GET');
        $response = $middleware->handle($request, function () {
            return new Response('ok');
        }, 'v2');

        expect($response->getContent())->toBe('ok');
    }
});

test('RateLimiter IP 限流 - 超过限制抛出异常', function () {
    $middleware = new RateLimiter;

    // 预设缓存使计数器达到限制
    $ip = '127.0.0.1';
    $key = "rate_limit_ip:v2:$ip";
    Cache::put($key, 201, 60);

    $request = Request::create('/api/v2/products', 'GET');

    $middleware->handle($request, function () {
        return new Response('ok');
    }, 'v2');
})->throws(\App\Exceptions\ApiResponseException::class);

test('RateLimiter ACME 模式仅检查 IP', function () {
    $middleware = new RateLimiter;
    $request = Request::create('/acme/new-order', 'POST', [], [], [], [], '{}');

    $response = $middleware->handle($request, function () {
        return new Response('ok');
    }, 'acme');

    expect($response->getContent())->toBe('ok');
});

test('RateLimiter 默认限流更严格', function () {
    $middleware = new RateLimiter;
    $ip = '127.0.0.1';
    $key = "rate_limit_ip:default:$ip";

    // 默认限制为 100，低于 v1/v2 的 200
    Cache::put($key, 101, 60);

    $request = Request::create('/api/other/path', 'GET');

    expect(fn () => $middleware->handle($request, function () {
        return new Response('ok');
    }, 'default'))->toThrow(\App\Exceptions\ApiResponseException::class);
});

test('RateLimiter 计数器在窗口期后重置', function () {
    $middleware = new RateLimiter;
    $ip = '127.0.0.1';
    $key = "rate_limit_ip:v2:$ip";

    // 模拟之前的计数器已过期
    expect(Cache::has($key))->toBeFalse();

    $request = Request::create('/api/v2/products', 'GET');
    $response = $middleware->handle($request, function () {
        return new Response('ok');
    }, 'v2');

    expect($response->getContent())->toBe('ok');
    expect((int) Cache::get($key))->toBe(1);
});

test('RateLimiter deploy 模式正常通过', function () {
    $middleware = new RateLimiter;
    $request = Request::create('/api/deploy/products', 'GET');

    $response = $middleware->handle($request, function () {
        return new Response('ok');
    }, 'deploy');

    expect($response->getContent())->toBe('ok');
});
