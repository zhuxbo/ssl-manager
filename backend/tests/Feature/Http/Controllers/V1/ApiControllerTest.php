<?php

use App\Models\ApiToken;
use App\Models\Cert;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * V1 API 使用 API Token 认证
 * 使用 createToken 获取明文 token（数据库存储 hash 后的值）
 */
function createV1AuthHeaders(User $user): array
{
    $plainToken = ApiToken::createToken($user->id);

    return ['Authorization' => "Bearer $plainToken"];
}

test('V1 健康检查', function () {
    $user = User::factory()->create();
    $headers = createV1AuthHeaders($user);

    $this->withHeaders($headers)
        ->getJson('/api/V1/health')
        ->assertOk()
        ->assertJson(['code' => 1, 'data' => ['status' => 'ok', 'version' => 'v1']]);
});

test('V1 获取产品列表', function () {
    $user = User::factory()->create();
    $headers = createV1AuthHeaders($user);
    Product::factory()->count(3)->create();

    $this->withHeaders($headers)
        ->postJson('/api/V1/product')
        ->assertOk()
        ->assertJson(['code' => 1]);
});

test('V1 获取产品列表-按品牌筛选', function () {
    $user = User::factory()->create();
    $headers = createV1AuthHeaders($user);
    Product::factory()->create(['brand' => 'TestBrand']);

    $this->withHeaders($headers)
        ->postJson('/api/V1/product', ['brand' => 'TestBrand'])
        ->assertOk()
        ->assertJson(['code' => 1]);
});

test('V1 获取订单', function () {
    $user = User::factory()->create();
    $headers = createV1AuthHeaders($user);
    $product = Product::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
    ]);
    $cert = Cert::factory()->active()->create([
        'order_id' => $order->id,
    ]);
    $order->update(['latest_cert_id' => $cert->id]);

    // 预设缓存跳过 sync/pay/commit 调用（避免调用上游 API）
    Cache::set('api_get_'.$order->id, time(), 120);

    $this->withHeaders($headers)
        ->postJson('/api/V1/get', ['oid' => $order->id])
        ->assertOk()
        ->assertJson(['code' => 1]);
});

test('V1 获取订单-不存在', function () {
    $user = User::factory()->create();
    $headers = createV1AuthHeaders($user);

    $this->withHeaders($headers)
        ->postJson('/api/V1/get', ['oid' => 99999])
        ->assertOk()
        ->assertJson(['code' => 0]);
});

test('V1 通过 refer_id 获取订单ID', function () {
    $user = User::factory()->create();
    $headers = createV1AuthHeaders($user);
    $product = Product::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
    ]);
    $cert = Cert::factory()->create([
        'order_id' => $order->id,
        'refer_id' => 'test-refer-v1',
    ]);
    $order->update(['latest_cert_id' => $cert->id]);

    $this->withHeaders($headers)
        ->postJson('/api/V1/getOidByReferId', ['refer_id' => 'test-refer-v1'])
        ->assertOk()
        ->assertJson(['code' => 1, 'data' => ['oid' => $order->id]]);
});

test('V1 通过 refer_id 获取订单ID-不存在', function () {
    $user = User::factory()->create();
    $headers = createV1AuthHeaders($user);

    $this->withHeaders($headers)
        ->postJson('/api/V1/getOidByReferId', ['refer_id' => 'nonexistent'])
        ->assertOk()
        ->assertJson(['code' => 0]);
});

test('V1 API-未认证', function () {
    $this->getJson('/api/V1/health')
        ->assertOk()
        ->assertJson(['code' => 0]);
});
