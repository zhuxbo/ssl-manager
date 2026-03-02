<?php

use App\Http\Middleware\AcmeJwsMiddleware;
use App\Models\Acme\Account;
use App\Services\Acme\AccountService;
use App\Services\Acme\JwsService;
use App\Services\Acme\NonceService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

beforeEach(function () {
    $this->jwsService = Mockery::mock(JwsService::class);
    $this->nonceService = Mockery::mock(NonceService::class);
    $this->accountService = Mockery::mock(AccountService::class);

    $this->middleware = new AcmeJwsMiddleware(
        $this->jwsService,
        $this->nonceService,
        $this->accountService
    );
});

test('GET 请求直接透传不验证 JWS', function () {
    $request = Request::create('/acme/directory', 'GET');

    $response = $this->middleware->handle($request, function () {
        return new Response('ok');
    });

    expect($response->getContent())->toBe('ok');
});

test('HEAD 请求直接透传不验证 JWS', function () {
    $request = Request::create('/acme/new-nonce', 'HEAD');

    $response = $this->middleware->handle($request, function () {
        return new Response('', 200);
    });

    expect($response->getStatusCode())->toBe(200);
});

test('POST 请求无效 JWS 格式返回 400', function () {
    $request = Request::create('/acme/new-order', 'POST', [], [], [], [], 'invalid-body');

    $this->jwsService->shouldReceive('parse')->andReturn(null);
    $this->nonceService->shouldReceive('generate')->andReturn('new-nonce');

    $response = $this->middleware->handle($request, function () {
        return new Response('ok');
    });

    expect($response->getStatusCode())->toBe(400);
    $data = json_decode($response->getContent(), true);
    expect($data['type'])->toContain('malformed');
});

test('POST 请求无效 Nonce 返回 400', function () {
    $request = Request::create('/acme/new-order', 'POST', [], [], [], [], '{}');

    $this->jwsService->shouldReceive('parse')->andReturn([
        'protected' => ['nonce' => 'bad-nonce', 'url' => '', 'kid' => 'kid-1'],
        'payload' => '',
        'signature' => '',
    ]);
    $this->nonceService->shouldReceive('verify')->with('bad-nonce')->andReturn(false);
    $this->nonceService->shouldReceive('generate')->andReturn('new-nonce');

    $response = $this->middleware->handle($request, function () {
        return new Response('ok');
    });

    expect($response->getStatusCode())->toBe(400);
    $data = json_decode($response->getContent(), true);
    expect($data['type'])->toContain('badNonce');
});

test('POST 请求 URL 不匹配返回 400', function () {
    $request = Request::create('http://localhost/acme/new-order', 'POST', [], [], [], [], '{}');

    $this->jwsService->shouldReceive('parse')->andReturn([
        'protected' => [
            'nonce' => 'valid-nonce',
            'url' => 'http://localhost/acme/different-url',
            'kid' => 'kid-1',
        ],
        'payload' => '',
        'signature' => '',
    ]);
    $this->nonceService->shouldReceive('verify')->andReturn(true);
    $this->nonceService->shouldReceive('generate')->andReturn('new-nonce');

    $response = $this->middleware->handle($request, function () {
        return new Response('ok');
    });

    expect($response->getStatusCode())->toBe(400);
    $data = json_decode($response->getContent(), true);
    expect($data['detail'])->toContain('URL mismatch');
});

test('JWK 和 KID 同时存在返回 400', function () {
    $url = 'http://localhost/acme/new-order';
    $request = Request::create($url, 'POST', [], [], [], [], '{}');

    $this->jwsService->shouldReceive('parse')->andReturn([
        'protected' => [
            'nonce' => 'valid-nonce',
            'url' => $url,
        ],
        'payload' => '',
        'signature' => '',
    ]);
    $this->nonceService->shouldReceive('verify')->andReturn(true);
    $this->nonceService->shouldReceive('generate')->andReturn('new-nonce');
    $this->jwsService->shouldReceive('extractPublicKey')->andReturn(['kty' => 'RSA']);
    $this->jwsService->shouldReceive('extractKid')->andReturn('kid-1');

    $response = $this->middleware->handle($request, function () {
        return new Response('ok');
    });

    expect($response->getStatusCode())->toBe(400);
    $data = json_decode($response->getContent(), true);
    expect($data['detail'])->toContain('mutually exclusive');
});

test('非 new-acct 路由无 KID 返回 400', function () {
    $url = 'http://localhost/acme/new-order';
    $request = Request::create($url, 'POST', [], [], [], [], '{}');

    $this->jwsService->shouldReceive('parse')->andReturn([
        'protected' => [
            'nonce' => 'valid-nonce',
            'url' => $url,
        ],
        'payload' => '',
        'signature' => '',
    ]);
    $this->nonceService->shouldReceive('verify')->andReturn(true);
    $this->nonceService->shouldReceive('generate')->andReturn('new-nonce');
    $this->jwsService->shouldReceive('extractPublicKey')->andReturn(['kty' => 'RSA']);
    $this->jwsService->shouldReceive('extractKid')->andReturn(null);

    $response = $this->middleware->handle($request, function () {
        return new Response('ok');
    });

    expect($response->getStatusCode())->toBe(400);
    $data = json_decode($response->getContent(), true);
    expect($data['detail'])->toContain('KID is required');
});

test('KID 对应的账户不存在返回 404', function () {
    $url = 'http://localhost/acme/new-order';
    $request = Request::create($url, 'POST', [], [], [], [], '{}');

    $this->jwsService->shouldReceive('parse')->andReturn([
        'protected' => [
            'nonce' => 'valid-nonce',
            'url' => $url,
        ],
        'payload' => '',
        'signature' => '',
    ]);
    $this->nonceService->shouldReceive('verify')->andReturn(true);
    $this->nonceService->shouldReceive('generate')->andReturn('new-nonce');
    $this->jwsService->shouldReceive('extractPublicKey')->andReturn(null);
    $this->jwsService->shouldReceive('extractKid')->andReturn('kid-not-found');
    $this->jwsService->shouldReceive('findAccountByKid')->with('kid-not-found')->andReturn(null);

    $response = $this->middleware->handle($request, function () {
        return new Response('ok');
    });

    expect($response->getStatusCode())->toBe(404);
    $data = json_decode($response->getContent(), true);
    expect($data['type'])->toContain('accountDoesNotExist');
});

test('停用的账户返回 401', function () {
    $url = 'http://localhost/acme/new-order';
    $request = Request::create($url, 'POST', [], [], [], [], '{}');

    $account = new Account(['status' => 'deactivated', 'public_key' => ['kty' => 'RSA']]);

    $this->jwsService->shouldReceive('parse')->andReturn([
        'protected' => [
            'nonce' => 'valid-nonce',
            'url' => $url,
        ],
        'payload' => '',
        'signature' => '',
    ]);
    $this->nonceService->shouldReceive('verify')->andReturn(true);
    $this->nonceService->shouldReceive('generate')->andReturn('new-nonce');
    $this->jwsService->shouldReceive('extractPublicKey')->andReturn(null);
    $this->jwsService->shouldReceive('extractKid')->andReturn('kid-1');
    $this->jwsService->shouldReceive('findAccountByKid')->andReturn($account);

    $response = $this->middleware->handle($request, function () {
        return new Response('ok');
    });

    expect($response->getStatusCode())->toBe(401);
});

test('签名验证失败返回 400', function () {
    $url = 'http://localhost/acme/new-order';
    $request = Request::create($url, 'POST', [], [], [], [], '{}');

    $account = new Account(['status' => 'valid', 'public_key' => ['kty' => 'RSA']]);

    $jws = [
        'protected' => [
            'nonce' => 'valid-nonce',
            'url' => $url,
        ],
        'payload' => '',
        'signature' => '',
    ];

    $this->jwsService->shouldReceive('parse')->andReturn($jws);
    $this->nonceService->shouldReceive('verify')->andReturn(true);
    $this->nonceService->shouldReceive('generate')->andReturn('new-nonce');
    $this->jwsService->shouldReceive('extractPublicKey')->andReturn(null);
    $this->jwsService->shouldReceive('extractKid')->andReturn('kid-1');
    $this->jwsService->shouldReceive('findAccountByKid')->andReturn($account);
    $this->jwsService->shouldReceive('verify')->andReturn(false);

    $response = $this->middleware->handle($request, function () {
        return new Response('ok');
    });

    expect($response->getStatusCode())->toBe(400);
    $data = json_decode($response->getContent(), true);
    expect($data['type'])->toContain('badSignatureAlgorithm');
});

test('new-acct 路由允许 JWK 认证', function () {
    $url = 'http://localhost/acme/new-acct';
    $request = Request::create($url, 'POST', [], [], [], [], '{}');

    $jws = [
        'protected' => [
            'nonce' => 'valid-nonce',
            'url' => $url,
        ],
        'payload' => '',
        'signature' => '',
    ];

    $jwk = ['kty' => 'RSA', 'n' => 'test', 'e' => 'AQAB'];

    $this->jwsService->shouldReceive('parse')->andReturn($jws);
    $this->nonceService->shouldReceive('verify')->andReturn(true);
    $this->jwsService->shouldReceive('extractPublicKey')->andReturn($jwk);
    $this->jwsService->shouldReceive('extractKid')->andReturn(null);
    $this->jwsService->shouldReceive('verify')->andReturn(true);
    $this->jwsService->shouldReceive('computeKeyId')->andReturn('computed-key-id');
    $this->accountService->shouldReceive('findByKeyId')->andReturn(null);

    $response = $this->middleware->handle($request, function () {
        return new Response('ok');
    });

    expect($response->getStatusCode())->toBe(200);
});

test('错误响应包含 Replay-Nonce 头', function () {
    $request = Request::create('/acme/new-order', 'POST', [], [], [], [], 'invalid');

    $this->jwsService->shouldReceive('parse')->andReturn(null);
    $this->nonceService->shouldReceive('generate')->andReturn('replay-nonce-value');

    $response = $this->middleware->handle($request, function () {
        return new Response('ok');
    });

    expect($response->headers->get('Replay-Nonce'))->toBe('replay-nonce-value');
    expect($response->headers->get('Content-Type'))->toContain('application/problem+json');
});
