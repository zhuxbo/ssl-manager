<?php

use App\Models\Cert;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;

uses(Tests\Traits\ActsAsUser::class, Tests\Traits\MocksExternalApis::class);

test('获取订单列表', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
    ]);
    $cert = Cert::factory()->create([
        'order_id' => $order->id,
        'status' => 'active',
    ]);
    $order->update(['latest_cert_id' => $cert->id]);

    $this->actingAsUser($user)
        ->getJson('/api/order')
        ->assertOk()
        ->assertJson(['code' => 1])
        ->assertJsonStructure(['data' => ['items', 'total', 'pageSize', 'currentPage']]);
});

test('获取订单列表-按状态筛选', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
    ]);
    $cert = Cert::factory()->active()->create([
        'order_id' => $order->id,
    ]);
    $order->update(['latest_cert_id' => $cert->id]);

    $this->actingAsUser($user)
        ->getJson('/api/order?statusSet=activating')
        ->assertOk()
        ->assertJson(['code' => 1]);
});

test('获取订单详情', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
    ]);
    $cert = Cert::factory()->active()->create([
        'order_id' => $order->id,
    ]);
    $order->update(['latest_cert_id' => $cert->id]);

    $this->actingAsUser($user)
        ->getJson("/api/order/$order->id")
        ->assertOk()
        ->assertJson(['code' => 1])
        ->assertJsonStructure(['data' => ['id', 'product_id', 'product', 'latest_cert']]);
});

test('获取订单详情-订单不存在', function () {
    $user = User::factory()->create();

    $this->actingAsUser($user)
        ->getJson('/api/order/99999')
        ->assertOk()
        ->assertJson(['code' => 0]);
});

test('新建订单', function () {
    $user = User::factory()->withBalance('1000.00')->create();
    $product = Product::factory()->create();

    $this->mockSdk();

    $this->actingAsUser($user)
        ->postJson('/api/order/new', [
            'product_id' => $product->id,
            'period' => 12,
            'domains' => 'example.com',
            'validation_method' => 'txt',
            'csr_generate' => 1,
        ])
        ->assertOk()
        ->assertJson(['code' => 1]);
});

test('续费订单', function () {
    $user = User::factory()->withBalance('1000.00')->create();
    $product = Product::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'period_till' => now()->addDays(15),
    ]);
    $cert = Cert::factory()->active()->create([
        'order_id' => $order->id,
    ]);
    $order->update(['latest_cert_id' => $cert->id]);

    $this->mockSdk();

    $this->actingAsUser($user)
        ->postJson('/api/order/renew', [
            'order_id' => $order->id,
            'period' => 12,
            'domains' => 'example.com',
            'validation_method' => 'txt',
            'csr_generate' => 1,
        ])
        ->assertOk()
        ->assertJson(['code' => 1]);
});

test('重签订单', function () {
    $user = User::factory()->withBalance('1000.00')->create();
    $product = Product::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
    ]);
    $cert = Cert::factory()->active()->create([
        'order_id' => $order->id,
    ]);
    $order->update(['latest_cert_id' => $cert->id]);

    $this->mockSdk();

    $this->actingAsUser($user)
        ->postJson('/api/order/reissue', [
            'order_id' => $order->id,
            'domains' => 'example.com',
            'validation_method' => 'txt',
            'csr_generate' => 1,
        ])
        ->assertOk()
        ->assertJson(['code' => 1]);
});

test('批量获取订单详情', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    $orders = [];
    for ($i = 0; $i < 3; $i++) {
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);
        $cert = Cert::factory()->active()->create([
            'order_id' => $order->id,
        ]);
        $order->update(['latest_cert_id' => $cert->id]);
        $orders[] = $order;
    }

    $ids = collect($orders)->pluck('id')->toArray();

    $this->actingAsUser($user)
        ->getJson('/api/order/batch?ids='.implode(',', $ids))
        ->assertOk()
        ->assertJson(['code' => 1]);
});

test('订单列表-未认证', function () {
    $this->getJson('/api/order')
        ->assertUnauthorized();
});
