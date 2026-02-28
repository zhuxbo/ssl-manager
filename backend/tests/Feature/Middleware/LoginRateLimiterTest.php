<?php

use App\Http\Middleware\LoginRateLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    Cache::flush();
    RateLimiter::clear('admin:127.0.0.1');
    RateLimiter::clear('admin:testuser');
});

test('LoginRateLimiter 正常请求透传', function () {
    $middleware = new LoginRateLimiter;
    $request = Request::create('/api/admin/login', 'POST', ['account' => 'testuser', 'password' => 'pass']);

    $response = $middleware->handle($request, function () {
        return new JsonResponse(['code' => 1, 'data' => ['access_token' => 'xxx']]);
    }, 'admin');

    expect($response->getData(true)['code'])->toBe(1);
});

test('LoginRateLimiter 登录失败增加计数器', function () {
    $middleware = new LoginRateLimiter;
    $request = Request::create('/api/admin/login', 'POST', ['account' => 'testuser', 'password' => 'wrong']);

    $middleware->handle($request, function () {
        return new JsonResponse(['code' => 0, 'msg' => '密码错误']);
    }, 'admin');

    expect((int) RateLimiter::attempts('admin:testuser'))->toBe(1);
});

test('LoginRateLimiter 登录成功重置计数器', function () {
    $middleware = new LoginRateLimiter;

    for ($i = 0; $i < 3; $i++) {
        $request = Request::create('/api/admin/login', 'POST', ['account' => 'testuser', 'password' => 'wrong']);
        $middleware->handle($request, function () {
            return new JsonResponse(['code' => 0, 'msg' => '密码错误']);
        }, 'admin');
    }
    expect((int) RateLimiter::attempts('admin:testuser'))->toBe(3);

    $request = Request::create('/api/admin/login', 'POST', ['account' => 'testuser', 'password' => 'correct']);
    $middleware->handle($request, function () {
        return new JsonResponse(['code' => 1, 'data' => ['access_token' => 'xxx']]);
    }, 'admin');

    expect((int) RateLimiter::attempts('admin:testuser'))->toBe(0);
});

test('LoginRateLimiter 超过限制抛出异常', function () {
    $middleware = new LoginRateLimiter;

    for ($i = 0; $i < 5; $i++) {
        $request = Request::create('/api/admin/login', 'POST', ['account' => 'testuser', 'password' => 'wrong']);
        $middleware->handle($request, function () {
            return new JsonResponse(['code' => 0, 'msg' => '密码错误']);
        }, 'admin');
    }

    $request = Request::create('/api/admin/login', 'POST', ['account' => 'testuser', 'password' => 'try']);
    $middleware->handle($request, function () {
        return new JsonResponse(['code' => 1]);
    }, 'admin');
})->throws(\App\Exceptions\ApiResponseException::class);

test('LoginRateLimiter 被锁定账号抛出异常', function () {
    Cache::put('admin:testuser_locked', true, now()->addHours(24));

    $middleware = new LoginRateLimiter;
    $request = Request::create('/api/admin/login', 'POST', ['account' => 'testuser', 'password' => 'pass']);

    $middleware->handle($request, function () {
        return new JsonResponse(['code' => 1]);
    }, 'admin');
})->throws(\App\Exceptions\ApiResponseException::class);

test('LoginRateLimiter 无 account 时使用 IP 作为 key', function () {
    $middleware = new LoginRateLimiter;
    $request = Request::create('/api/admin/login', 'POST');

    $middleware->handle($request, function () {
        return new JsonResponse(['code' => 0, 'msg' => '账号不能为空']);
    }, 'admin');

    expect((int) RateLimiter::attempts('admin:127.0.0.1'))->toBe(1);
});
