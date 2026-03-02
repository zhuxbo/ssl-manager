<?php

use App\Models\Cert;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\SettingGroup;
use App\Models\User;
use App\Services\Order\Action;

test('SSL 回调-缺少 token', function () {
    $this->postJson('/callback/ssl', [])
        ->assertOk()
        ->assertJson(['code' => 0]);
});

test('SSL 回调-token 无效', function () {
    // 设置 callbackToken
    $group = SettingGroup::firstOrCreate(
        ['name' => 'site'],
        ['title' => 'Site', 'weight' => 0]
    );
    Setting::updateOrCreate(
        ['group_id' => $group->id, 'key' => 'callbackToken'],
        ['value' => 'valid_token', 'type' => 'string']
    );

    $this->postJson('/callback/ssl', [
        'token' => 'invalid_token',
    ])
        ->assertOk()
        ->assertJson(['code' => 0]);
});

test('SSL 回调-IP 不在白名单', function () {
    $group = SettingGroup::firstOrCreate(
        ['name' => 'site'],
        ['title' => 'Site', 'weight' => 0]
    );
    Setting::updateOrCreate(
        ['group_id' => $group->id, 'key' => 'callbackToken'],
        ['value' => 'valid_token', 'type' => 'string']
    );
    Setting::updateOrCreate(
        ['group_id' => $group->id, 'key' => 'callbackAllowedIps'],
        ['value' => json_encode(['8.8.8.8'], JSON_THROW_ON_ERROR), 'type' => 'array']
    );

    $this->postJson('/callback/ssl', [
        'token' => 'valid_token',
        'id' => 'any-id',
    ])
        ->assertOk()
        ->assertJson(['code' => 0, 'msg' => 'IP not allowed']);
});

test('SSL 回调-订单不存在', function () {
    $group = SettingGroup::firstOrCreate(
        ['name' => 'site'],
        ['title' => 'Site', 'weight' => 0]
    );
    Setting::updateOrCreate(
        ['group_id' => $group->id, 'key' => 'callbackToken'],
        ['value' => 'valid_token', 'type' => 'string']
    );

    $this->postJson('/callback/ssl', [
        'token' => 'valid_token',
        'id' => 'nonexistent-api-id',
    ])
        ->assertOk()
        ->assertJson(['code' => 0]);
});

test('SSL 回调-成功触发同步', function () {
    $group = SettingGroup::firstOrCreate(
        ['name' => 'site'],
        ['title' => 'Site', 'weight' => 0]
    );
    Setting::updateOrCreate(
        ['group_id' => $group->id, 'key' => 'callbackToken'],
        ['value' => 'valid_token', 'type' => 'string']
    );

    $user = User::factory()->create();
    $product = Product::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
    ]);
    $cert = Cert::factory()->create([
        'order_id' => $order->id,
        'api_id' => 'callback-api-id',
        'status' => 'processing',
    ]);
    $order->update(['latest_cert_id' => $cert->id]);

    $mockAction = Mockery::mock(Action::class);
    $mockAction->shouldReceive('createTask')->with($order->id, 'sync')->once();
    app()->instance(Action::class, $mockAction);

    $this->postJson('/callback/ssl', [
        'token' => 'valid_token',
        'id' => 'callback-api-id',
    ])
        ->assertOk()
        ->assertJson(['code' => 1]);
});

test('SSL 回调-使用 orderId 参数', function () {
    $group = SettingGroup::firstOrCreate(
        ['name' => 'site'],
        ['title' => 'Site', 'weight' => 0]
    );
    Setting::updateOrCreate(
        ['group_id' => $group->id, 'key' => 'callbackToken'],
        ['value' => 'valid_token', 'type' => 'string']
    );

    $user = User::factory()->create();
    $product = Product::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
    ]);
    $cert = Cert::factory()->create([
        'order_id' => $order->id,
        'api_id' => 'callback-order-id',
        'status' => 'active',
    ]);
    $order->update(['latest_cert_id' => $cert->id]);

    $mockAction = Mockery::mock(Action::class);
    $mockAction->shouldReceive('createTask')->with($order->id, 'sync')->once();
    app()->instance(Action::class, $mockAction);

    $this->postJson('/callback/ssl', [
        'token' => 'valid_token',
        'orderId' => 'callback-order-id',
    ])
        ->assertOk()
        ->assertJson(['code' => 1]);
});

test('SSL 回调-使用 password 参数认证', function () {
    $group = SettingGroup::firstOrCreate(
        ['name' => 'site'],
        ['title' => 'Site', 'weight' => 0]
    );
    Setting::updateOrCreate(
        ['group_id' => $group->id, 'key' => 'callbackToken'],
        ['value' => 'valid_token', 'type' => 'string']
    );

    $user = User::factory()->create();
    $product = Product::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
    ]);
    $cert = Cert::factory()->create([
        'order_id' => $order->id,
        'api_id' => 'password-api-id',
        'status' => 'approving',
    ]);
    $order->update(['latest_cert_id' => $cert->id]);

    $mockAction = Mockery::mock(Action::class);
    $mockAction->shouldReceive('createTask')->with($order->id, 'sync')->once();
    app()->instance(Action::class, $mockAction);

    $this->postJson('/callback/ssl', [
        'password' => 'valid_token',
        'id' => 'password-api-id',
    ])
        ->assertOk()
        ->assertJson(['code' => 1]);
});

test('SSL 回调-非可同步状态不触发同步任务', function () {
    $group = SettingGroup::firstOrCreate(
        ['name' => 'site'],
        ['title' => 'Site', 'weight' => 0]
    );
    Setting::updateOrCreate(
        ['group_id' => $group->id, 'key' => 'callbackToken'],
        ['value' => 'valid_token', 'type' => 'string']
    );

    $user = User::factory()->create();
    $product = Product::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
    ]);
    $cert = Cert::factory()->create([
        'order_id' => $order->id,
        'api_id' => 'no-sync-api-id',
        'status' => 'cancelled',
    ]);
    $order->update(['latest_cert_id' => $cert->id]);

    $mockAction = Mockery::mock(Action::class);
    $mockAction->shouldReceive('createTask')->never();
    app()->instance(Action::class, $mockAction);

    $this->postJson('/callback/ssl', [
        'token' => 'valid_token',
        'id' => 'no-sync-api-id',
    ])
        ->assertOk()
        ->assertJson(['code' => 1]);
});

test('SSL 回调-支持 TOKEN 服务器变量认证', function () {
    $group = SettingGroup::firstOrCreate(
        ['name' => 'site'],
        ['title' => 'Site', 'weight' => 0]
    );
    Setting::updateOrCreate(
        ['group_id' => $group->id, 'key' => 'callbackToken'],
        ['value' => 'valid_token', 'type' => 'string']
    );

    $user = User::factory()->create();
    $product = Product::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
    ]);
    $cert = Cert::factory()->create([
        'order_id' => $order->id,
        'api_id' => 'server-token-id',
        'status' => 'processing',
    ]);
    $order->update(['latest_cert_id' => $cert->id]);

    $mockAction = Mockery::mock(Action::class);
    $mockAction->shouldReceive('createTask')->with($order->id, 'sync')->once();
    app()->instance(Action::class, $mockAction);

    $this->withServerVariables(['TOKEN' => 'valid_token'])
        ->postJson('/callback/ssl', [
            'id' => 'server-token-id',
        ])
        ->assertOk()
        ->assertJson(['code' => 1]);
});
