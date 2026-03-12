<?php

use App\Models\Acme\AcmeOrder;
use App\Models\Product;
use App\Models\User;
use App\Services\Acme\BillingService;

uses(Tests\Traits\ActsAsUser::class);

test('创建 ACME 订阅订单', function () {
    $user = User::factory()->withBalance('1000.00')->create();
    $product = Product::factory()->create(['product_type' => 'acme']);

    $order = AcmeOrder::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
    ]);

    $mockBillingService = Mockery::mock(BillingService::class);
    $mockBillingService->shouldReceive('createSubscription')
        ->once()
        ->andReturn([
            'code' => 1,
            'data' => [
                'order' => $order,
                'eab_kid' => 'test_kid',
                'eab_hmac' => 'test_hmac',
            ],
        ]);
    app()->instance(BillingService::class, $mockBillingService);

    $this->actingAsUser($user)
        ->postJson('/api/acme/order', [
            'product_id' => $product->id,
            'period' => 12,
            'domains' => 'example.com',
            'validation_method' => 'txt',
        ])
        ->assertOk()
        ->assertJson(['code' => 1])
        ->assertJsonStructure(['data' => ['order_id', 'eab_kid', 'eab_hmac']]);
});

test('创建 ACME 订阅订单-余额不足', function () {
    $user = User::factory()->create(['balance' => '0.00']);
    $product = Product::factory()->create(['product_type' => 'acme']);

    $mockBillingService = Mockery::mock(BillingService::class);
    $mockBillingService->shouldReceive('createSubscription')
        ->once()
        ->andReturn([
            'code' => 0,
            'msg' => '余额不足',
        ]);
    app()->instance(BillingService::class, $mockBillingService);

    $this->actingAsUser($user)
        ->postJson('/api/acme/order', [
            'product_id' => $product->id,
            'period' => 12,
            'domains' => 'example.com',
            'validation_method' => 'txt',
        ])
        ->assertOk()
        ->assertJson(['code' => 0]);
});

test('创建 ACME 订阅订单-缺少 domains 返回验证错误', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['product_type' => 'acme']);

    $this->actingAsUser($user)
        ->postJson('/api/acme/order', [
            'product_id' => $product->id,
            'period' => 12,
            'validation_method' => 'txt',
        ])
        ->assertOk()
        ->assertJson(['code' => 0]);
});

test('创建 ACME 订阅订单-无效 validation_method 返回验证错误', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['product_type' => 'acme']);

    $this->actingAsUser($user)
        ->postJson('/api/acme/order', [
            'product_id' => $product->id,
            'period' => 12,
            'domains' => 'example.com',
            'validation_method' => 'invalid',
        ])
        ->assertOk()
        ->assertJson(['code' => 0]);
});

test('获取 EAB 凭据', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['product_type' => 'acme']);
    $order = AcmeOrder::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
    ]);

    $this->actingAsUser($user)
        ->getJson("/api/acme/eab/$order->id")
        ->assertOk()
        ->assertJson(['code' => 1])
        ->assertJsonStructure(['data' => ['eab_kid', 'eab_hmac', 'server_url', 'certbot_command', 'acmesh_command']]);
});

test('获取 EAB 凭据-订单不属于当前用户', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $product = Product::factory()->create(['product_type' => 'acme']);
    $order = AcmeOrder::factory()->create([
        'user_id' => $otherUser->id,
        'product_id' => $product->id,
    ]);

    $this->actingAsUser($user)
        ->getJson("/api/acme/eab/$order->id")
        ->assertStatus(404);
});

test('ACME 订单-未认证', function () {
    $this->postJson('/api/acme/order', [
        'product_id' => 1,
        'period' => 12,
        'domains' => 'example.com',
        'validation_method' => 'txt',
    ])
        ->assertUnauthorized();
});
