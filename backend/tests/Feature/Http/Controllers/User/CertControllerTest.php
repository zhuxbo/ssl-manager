<?php

use App\Models\Cert;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;

uses(Tests\Traits\ActsAsUser::class);

test('获取证书列表', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
    ]);
    Cert::factory()->active()->create([
        'order_id' => $order->id,
    ]);

    $this->actingAsUser($user)
        ->getJson('/api/cert')
        ->assertOk()
        ->assertJson(['code' => 1])
        ->assertJsonStructure(['data' => ['items', 'total', 'pageSize', 'currentPage']]);
});

test('获取证书列表-按订单ID筛选', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
    ]);
    Cert::factory()->active()->create([
        'order_id' => $order->id,
    ]);

    $this->actingAsUser($user)
        ->getJson("/api/cert?order_id=$order->id")
        ->assertOk()
        ->assertJson(['code' => 1]);
});

test('获取证书列表-按状态筛选', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
    ]);
    Cert::factory()->active()->create([
        'order_id' => $order->id,
    ]);

    $this->actingAsUser($user)
        ->getJson('/api/cert?status=active')
        ->assertOk()
        ->assertJson(['code' => 1]);
});

test('获取证书详情', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
    ]);
    $cert = Cert::factory()->active()->create([
        'order_id' => $order->id,
    ]);

    $this->actingAsUser($user)
        ->getJson("/api/cert/$cert->id")
        ->assertOk()
        ->assertJson(['code' => 1])
        ->assertJsonStructure(['data' => ['id', 'order_id', 'common_name', 'status']]);
});

test('获取证书详情-证书不存在', function () {
    $user = User::factory()->create();

    $this->actingAsUser($user)
        ->getJson('/api/cert/99999')
        ->assertOk()
        ->assertJson(['code' => 0]);
});

test('批量获取证书详情', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
    ]);

    $certs = [];
    for ($i = 0; $i < 3; $i++) {
        $certs[] = Cert::factory()->active()->create([
            'order_id' => $order->id,
        ]);
    }

    $ids = collect($certs)->pluck('id')->toArray();

    $this->actingAsUser($user)
        ->getJson('/api/cert/batch?ids='.implode(',', $ids))
        ->assertOk()
        ->assertJson(['code' => 1]);
});

test('证书列表-未认证', function () {
    $this->getJson('/api/cert')
        ->assertUnauthorized();
});
