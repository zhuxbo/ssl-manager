<?php

use App\Models\Cert;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Delegation\CnameDelegationService;

test('签名为 schedule:validate', function () {
    $this->artisan('schedule:validate')->assertSuccessful();
});

test('无待验证订单时正常退出', function () {
    $this->artisan('schedule:validate')
        ->expectsOutputToContain('待验证订单数量: 0')
        ->assertSuccessful();
});

test('processing 状态证书会被检测', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
    ]);

    $cert = Cert::factory()->create([
        'order_id' => $order->id,
        'status' => 'processing',
        'dcv' => ['method' => 'txt'],
        'validation' => [
            ['domain' => 'example.com', 'method' => 'txt', 'value' => 'token-value'],
        ],
    ]);
    $order->update(['latest_cert_id' => $cert->id]);

    $this->artisan('schedule:validate')
        ->expectsOutputToContain('待验证订单数量: 1')
        ->assertSuccessful();
});

test('approving 状态证书会创建同步任务', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
    ]);

    $cert = Cert::factory()->create([
        'order_id' => $order->id,
        'status' => 'approving',
        'dcv' => ['method' => 'email'],
        'validation' => [['domain' => 'example.com', 'method' => 'email']],
    ]);
    $order->update(['latest_cert_id' => $cert->id]);

    $this->artisan('schedule:validate')
        ->expectsOutputToContain('待验证订单数量: 1')
        ->assertSuccessful();
});

test('pending 状态证书不参与验证', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
    ]);

    $cert = Cert::factory()->create([
        'order_id' => $order->id,
        'status' => 'pending',
        'dcv' => ['method' => 'txt'],
        'validation' => [],
    ]);
    $order->update(['latest_cert_id' => $cert->id]);

    $this->artisan('schedule:validate')
        ->expectsOutputToContain('待验证订单数量: 0')
        ->assertSuccessful();
});
