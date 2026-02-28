<?php

use App\Services\Acme\NonceService;

test('GET 获取 Nonce', function () {
    $mockNonce = Mockery::mock(NonceService::class);
    $mockNonce->shouldReceive('generate')->once()->andReturn('test-nonce-12345');
    app()->instance(NonceService::class, $mockNonce);

    $response = $this->getJson('/acme/new-nonce');
    $response->assertOk()
        ->assertHeader('Replay-Nonce', 'test-nonce-12345');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

test('HEAD 获取 Nonce', function () {
    $mockNonce = Mockery::mock(NonceService::class);
    $mockNonce->shouldReceive('generate')->once()->andReturn('test-nonce-head');
    app()->instance(NonceService::class, $mockNonce);

    $response = $this->call('HEAD', '/acme/new-nonce');
    $response->assertOk();
    $response->assertHeader('Replay-Nonce', 'test-nonce-head');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

test('Nonce 每次请求都不同', function () {
    $callCount = 0;
    $mockNonce = Mockery::mock(NonceService::class);
    $mockNonce->shouldReceive('generate')->andReturnUsing(function () use (&$callCount) {
        $callCount++;
        return "nonce-$callCount";
    });
    app()->instance(NonceService::class, $mockNonce);

    $response1 = $this->getJson('/acme/new-nonce');
    $nonce1 = $response1->headers->get('Replay-Nonce');

    $response2 = $this->getJson('/acme/new-nonce');
    $nonce2 = $response2->headers->get('Replay-Nonce');

    expect($nonce1)->not->toBe($nonce2);
});
