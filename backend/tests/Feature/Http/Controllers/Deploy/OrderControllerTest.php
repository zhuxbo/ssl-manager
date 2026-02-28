<?php

use App\Models\Cert;
use App\Models\DeployToken;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;

/**
 * Deploy API 使用 Deploy Token 认证
 */
function actingAsDeployUser(?User $user = null): \Illuminate\Testing\TestResponse
{
    $user ??= User::factory()->create();
    $deployToken = DeployToken::factory()->create(['user_id' => $user->id]);

    return test()->withHeaders([
        'Authorization' => "Bearer $deployToken->token",
    ]);
}

test('查询订单-无参数返回最新活跃订单', function () {
    $user = User::factory()->create();
    $deployToken = DeployToken::factory()->create(['user_id' => $user->id]);
    $product = Product::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
    ]);
    $cert = Cert::factory()->active()->create([
        'order_id' => $order->id,
    ]);
    $order->update(['latest_cert_id' => $cert->id]);

    test()->withHeaders(['Authorization' => "Bearer $deployToken->token"])
        ->getJson('/api/deploy/')
        ->assertOk()
        ->assertJson(['code' => 1]);
});

test('查询订单-按 order_id', function () {
    $user = User::factory()->create();
    $deployToken = DeployToken::factory()->create(['user_id' => $user->id]);
    $product = Product::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
    ]);
    $cert = Cert::factory()->active()->create([
        'order_id' => $order->id,
    ]);
    $order->update(['latest_cert_id' => $cert->id]);

    test()->withHeaders(['Authorization' => "Bearer $deployToken->token"])
        ->getJson("/api/deploy/?order_id=$order->id")
        ->assertOk()
        ->assertJson(['code' => 1]);
});

test('查询订单-按域名', function () {
    $user = User::factory()->create();
    $deployToken = DeployToken::factory()->create(['user_id' => $user->id]);
    $product = Product::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
    ]);
    $cert = Cert::factory()->active()->create([
        'order_id' => $order->id,
        'common_name' => 'deploy.example.com',
        'alternative_names' => 'deploy.example.com',
    ]);
    $order->update(['latest_cert_id' => $cert->id]);

    test()->withHeaders(['Authorization' => "Bearer $deployToken->token"])
        ->getJson('/api/deploy/?domain=deploy.example.com')
        ->assertOk()
        ->assertJson(['code' => 1]);
});

test('查询订单-域名不存在', function () {
    $user = User::factory()->create();
    $deployToken = DeployToken::factory()->create(['user_id' => $user->id]);

    test()->withHeaders(['Authorization' => "Bearer $deployToken->token"])
        ->getJson('/api/deploy/?domain=nonexistent.example.com')
        ->assertOk()
        ->assertJson(['code' => 0]);
});

test('部署回调-成功', function () {
    $user = User::factory()->create();
    $deployToken = DeployToken::factory()->create(['user_id' => $user->id]);
    $product = Product::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
    ]);
    $cert = Cert::factory()->active()->create([
        'order_id' => $order->id,
    ]);
    $order->update(['latest_cert_id' => $cert->id]);

    test()->withHeaders(['Authorization' => "Bearer $deployToken->token"])
        ->postJson('/api/deploy/callback', [
            'order_id' => $order->id,
            'domain' => 'example.com',
            'status' => 'success',
        ])
        ->assertOk()
        ->assertJson(['code' => 1, 'data' => ['recorded' => true]]);
});

test('部署回调-失败状态', function () {
    $user = User::factory()->create();
    $deployToken = DeployToken::factory()->create(['user_id' => $user->id]);
    $product = Product::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
    ]);
    $cert = Cert::factory()->active()->create([
        'order_id' => $order->id,
    ]);
    $order->update(['latest_cert_id' => $cert->id]);

    test()->withHeaders(['Authorization' => "Bearer $deployToken->token"])
        ->postJson('/api/deploy/callback', [
            'order_id' => $order->id,
            'domain' => 'example.com',
            'status' => 'failure',
            'message' => 'Connection refused',
        ])
        ->assertOk()
        ->assertJson(['code' => 1, 'data' => ['recorded' => false]]);
});

test('部署回调-订单不存在', function () {
    $user = User::factory()->create();
    $deployToken = DeployToken::factory()->create(['user_id' => $user->id]);

    test()->withHeaders(['Authorization' => "Bearer $deployToken->token"])
        ->postJson('/api/deploy/callback', [
            'order_id' => 99999,
            'domain' => 'example.com',
            'status' => 'success',
        ])
        ->assertOk()
        ->assertJson(['code' => 0]);
});

test('Deploy API-未认证', function () {
    $this->getJson('/api/deploy/')
        ->assertOk()
        ->assertJson(['code' => 0]);
});
