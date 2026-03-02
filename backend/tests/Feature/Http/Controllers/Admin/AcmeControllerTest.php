<?php

use App\Models\Admin;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Acme\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\Traits\ActsAsAdmin::class);
uses(Tests\Traits\MocksExternalApis::class);
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::factory()->create();
    $this->user = User::factory()->create();
    $this->product = Product::factory()->create(['support_acme' => 1]);
});

test('管理员可以创建ACME订阅订单', function () {
    $mock = Mockery::mock(BillingService::class);
    $mock->shouldReceive('createSubscription')
        ->once()
        ->andReturn(['code' => 1, 'order_id' => 1]);
    $this->app->instance(BillingService::class, $mock);

    $response = $this->actingAsAdmin($this->admin)->postJson('/api/admin/acme/order', [
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
        'period' => 12,
    ]);

    $response->assertOk()->assertJson(['code' => 1]);
    $response->assertJsonPath('data.created', 1);
});

test('管理员可以批量创建ACME订阅订单', function () {
    $mock = Mockery::mock(BillingService::class);
    $mock->shouldReceive('createSubscription')
        ->times(3)
        ->andReturn(['code' => 1, 'order_id' => 1]);
    $this->app->instance(BillingService::class, $mock);

    $response = $this->actingAsAdmin($this->admin)->postJson('/api/admin/acme/order', [
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
        'period' => 12,
        'quantity' => 3,
    ]);

    $response->assertOk()->assertJson(['code' => 1]);
    $response->assertJsonPath('data.created', 3);
});

test('创建ACME订单失败返回错误', function () {
    $mock = Mockery::mock(BillingService::class);
    $mock->shouldReceive('createSubscription')
        ->once()
        ->andReturn(['code' => 0, 'msg' => '余额不足']);
    $this->app->instance(BillingService::class, $mock);

    $response = $this->actingAsAdmin($this->admin)->postJson('/api/admin/acme/order', [
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
        'period' => 12,
    ]);

    $response->assertOk()->assertJson(['code' => 0]);
});

test('管理员可以获取订单EAB信息', function () {
    $order = Order::factory()->acme()->create([
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
    ]);

    $response = $this->actingAsAdmin($this->admin)->getJson("/api/admin/acme/eab/$order->id");

    $response->assertOk()->assertJson(['code' => 1]);
    $response->assertJsonStructure(['data' => [
        'order_id', 'eab_kid', 'eab_hmac', 'eab_used',
        'server_url', 'certbot_command', 'acmesh_command',
    ]]);
});

test('获取不存在的订单EAB信息返回404', function () {
    $response = $this->actingAsAdmin($this->admin)->getJson('/api/admin/acme/eab/99999');

    $response->assertNotFound();
});

test('获取无EAB的订单EAB信息返回404', function () {
    $order = Order::factory()->create([
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
        'eab_kid' => null,
    ]);

    $response = $this->actingAsAdmin($this->admin)->getJson("/api/admin/acme/eab/$order->id");

    $response->assertNotFound();
});

test('创建ACME订单缺少必要参数返回验证错误', function () {
    $response = $this->actingAsAdmin($this->admin)->postJson('/api/admin/acme/order', []);

    $response->assertOk()->assertJson(['code' => 0]);
});

test('未认证用户无法访问ACME管理', function () {
    $response = $this->postJson('/api/admin/acme/order', [
        'user_id' => 1,
        'product_id' => 1,
        'period' => 12,
    ]);

    $response->assertUnauthorized();
});
