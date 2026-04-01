<?php

use App\Models\Cert;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\SettingGroup;
use App\Models\User;
use App\Services\Order\Action;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// 辅助函数：设置回调端点配置
function setupCallbackEndpoint(string $endpoint, array $config): void
{
    $group = SettingGroup::firstOrCreate(
        ['name' => 'callback'],
        ['title' => '回调设置']
    );
    Setting::updateOrCreate(
        ['group_id' => $group->id, 'key' => $endpoint],
        ['type' => 'array', 'value' => $config]
    );
}

// 辅助函数：创建订单及证书数据
function createCallbackTestOrder(array $productOverrides = [], array $certOverrides = []): array
{
    $user = User::factory()->create();
    $product = Product::factory()->create($productOverrides);
    $order = Order::factory()->create(['user_id' => $user->id, 'product_id' => $product->id]);
    $cert = Cert::factory()->create(array_merge(['order_id' => $order->id], $certOverrides));
    $order->update(['latest_cert_id' => $cert->id]);

    return ['user' => $user, 'product' => $product, 'order' => $order, 'cert' => $cert];
}

// ==========================================
// 基础流程
// ==========================================

test('端点配置存在时正常流程', function () {
    setupCallbackEndpoint('certum', [
        'sources' => 'certum',
        'token' => 'test-token',
        'id_field' => 'orderId',
        'allowed_ips' => '',
    ]);

    $mockAction = Mockery::mock(Action::class);
    $mockAction->shouldReceive('createTask')->once();
    app()->instance(Action::class, $mockAction);

    $data = createCallbackTestOrder(
        ['source' => 'certum'],
        ['api_id' => 'certum-api-123', 'status' => 'processing'],
    );

    $this->postJson('/callback/certum', [
        'orderId' => 'certum-api-123',
        'token' => 'test-token',
    ])->assertOk()
        ->assertJson(['code' => 1]);
});

test('端点不存在时回落到 default', function () {
    setupCallbackEndpoint('default', [
        'sources' => '',
        'token' => 'default-token',
        'id_field' => 'id',
        'allowed_ips' => '',
    ]);

    $mockAction = Mockery::mock(Action::class);
    $mockAction->shouldReceive('createTask')->once();
    app()->instance(Action::class, $mockAction);

    createCallbackTestOrder(
        [],
        ['api_id' => 'fallback-api-123', 'status' => 'processing'],
    );

    $this->postJson('/callback/unknown', [
        'id' => 'fallback-api-123',
        'token' => 'default-token',
    ])->assertOk()
        ->assertJson(['code' => 1]);
});

test('端点不存在且无 default 返回错误', function () {
    $this->postJson('/callback/nonexistent', [
        'id' => 'test',
    ])->assertOk()
        ->assertJson(['code' => 0]);
});

test('endpoint 为空时使用 default', function () {
    setupCallbackEndpoint('default', [
        'sources' => '',
        'token' => '',
        'id_field' => 'id',
        'allowed_ips' => '',
    ]);

    $mockAction = Mockery::mock(Action::class);
    $mockAction->shouldReceive('createTask')->once();
    app()->instance(Action::class, $mockAction);

    createCallbackTestOrder(
        [],
        ['api_id' => 'default-api-123', 'status' => 'processing'],
    );

    $this->postJson('/callback', [
        'id' => 'default-api-123',
    ])->assertOk()
        ->assertJson(['code' => 1]);
});

// ==========================================
// Token 校验
// ==========================================

test('token 非空但缺少 token 参数返回错误', function () {
    setupCallbackEndpoint('certum', [
        'sources' => 'certum',
        'token' => 'required-token',
        'id_field' => 'id',
        'allowed_ips' => '',
    ]);

    $this->postJson('/callback/certum', [
        'id' => 'test-api-id',
    ])->assertOk()
        ->assertJson(['code' => 0]);
});

test('token 非空但传入错误 token 返回错误', function () {
    setupCallbackEndpoint('certum', [
        'sources' => 'certum',
        'token' => 'correct-token',
        'id_field' => 'id',
        'allowed_ips' => '',
    ]);

    $this->postJson('/callback/certum', [
        'id' => 'test-api-id',
        'token' => 'wrong-token',
    ])->assertOk()
        ->assertJson(['code' => 0]);
});

test('token 为空时不校验直接通过', function () {
    setupCallbackEndpoint('certum', [
        'sources' => '',
        'token' => '',
        'id_field' => 'id',
        'allowed_ips' => '',
    ]);

    $mockAction = Mockery::mock(Action::class);
    $mockAction->shouldReceive('createTask')->once();
    app()->instance(Action::class, $mockAction);

    createCallbackTestOrder(
        [],
        ['api_id' => 'no-token-api', 'status' => 'processing'],
    );

    $this->postJson('/callback/certum', [
        'id' => 'no-token-api',
    ])->assertOk()
        ->assertJson(['code' => 1]);
});

test('支持 password 参数作为 token', function () {
    setupCallbackEndpoint('certum', [
        'sources' => '',
        'token' => 'pw-token',
        'id_field' => 'id',
        'allowed_ips' => '',
    ]);

    $mockAction = Mockery::mock(Action::class);
    $mockAction->shouldReceive('createTask')->once();
    app()->instance(Action::class, $mockAction);

    createCallbackTestOrder(
        [],
        ['api_id' => 'pw-api-123', 'status' => 'processing'],
    );

    $this->postJson('/callback/certum', [
        'id' => 'pw-api-123',
        'password' => 'pw-token',
    ])->assertOk()
        ->assertJson(['code' => 1]);
});

test('支持 TOKEN 服务器变量作为 token', function () {
    setupCallbackEndpoint('certum', [
        'sources' => '',
        'token' => 'server-token',
        'id_field' => 'id',
        'allowed_ips' => '',
    ]);

    $mockAction = Mockery::mock(Action::class);
    $mockAction->shouldReceive('createTask')->once();
    app()->instance(Action::class, $mockAction);

    createCallbackTestOrder(
        [],
        ['api_id' => 'server-token-api', 'status' => 'processing'],
    );

    $this->withServerVariables(['TOKEN' => 'server-token'])
        ->postJson('/callback/certum', [
            'id' => 'server-token-api',
        ])->assertOk()
        ->assertJson(['code' => 1]);
});

// ==========================================
// IP 白名单
// ==========================================

test('allowed_ips 非空且 IP 不在列表中返回错误', function () {
    setupCallbackEndpoint('certum', [
        'sources' => '',
        'token' => '',
        'id_field' => 'id',
        'allowed_ips' => '10.0.0.1,10.0.0.2',
    ]);

    $this->postJson('/callback/certum', [
        'id' => 'test',
    ])->assertOk()
        ->assertJson(['code' => 0]);
});

test('多 IP 逗号分隔且 IP 在列表中通过', function () {
    setupCallbackEndpoint('certum', [
        'sources' => '',
        'token' => '',
        'id_field' => 'id',
        'allowed_ips' => '1.2.3.4, 127.0.0.1',
    ]);

    $mockAction = Mockery::mock(Action::class);
    $mockAction->shouldReceive('createTask')->once();
    app()->instance(Action::class, $mockAction);

    createCallbackTestOrder(
        [],
        ['api_id' => 'ip-ok-api', 'status' => 'processing'],
    );

    $this->postJson('/callback/certum', [
        'id' => 'ip-ok-api',
    ])->assertOk()
        ->assertJson(['code' => 1]);
});

test('allowed_ips 为空时不校验 IP', function () {
    setupCallbackEndpoint('certum', [
        'sources' => '',
        'token' => '',
        'id_field' => 'id',
        'allowed_ips' => '',
    ]);

    $mockAction = Mockery::mock(Action::class);
    $mockAction->shouldReceive('createTask')->once();
    app()->instance(Action::class, $mockAction);

    createCallbackTestOrder(
        [],
        ['api_id' => 'no-ip-check-api', 'status' => 'processing'],
    );

    $this->postJson('/callback/certum', [
        'id' => 'no-ip-check-api',
    ])->assertOk()
        ->assertJson(['code' => 1]);
});

// ==========================================
// id_field
// ==========================================

test('配置 id_field 从指定参数取值', function () {
    setupCallbackEndpoint('certum', [
        'sources' => '',
        'token' => '',
        'id_field' => 'orderId',
        'allowed_ips' => '',
    ]);

    $mockAction = Mockery::mock(Action::class);
    $mockAction->shouldReceive('createTask')->once();
    app()->instance(Action::class, $mockAction);

    createCallbackTestOrder(
        [],
        ['api_id' => 'custom-field-api', 'status' => 'processing'],
    );

    $this->postJson('/callback/certum', [
        'orderId' => 'custom-field-api',
    ])->assertOk()
        ->assertJson(['code' => 1]);
});

test('id_field 为空时默认取 id 参数', function () {
    setupCallbackEndpoint('certum', [
        'sources' => '',
        'token' => '',
        'id_field' => '',
        'allowed_ips' => '',
    ]);

    $mockAction = Mockery::mock(Action::class);
    $mockAction->shouldReceive('createTask')->once();
    app()->instance(Action::class, $mockAction);

    createCallbackTestOrder(
        [],
        ['api_id' => 'default-id-api', 'status' => 'processing'],
    );

    $this->postJson('/callback/certum', [
        'id' => 'default-id-api',
    ])->assertOk()
        ->assertJson(['code' => 1]);
});

test('ID 值为空返回错误', function () {
    setupCallbackEndpoint('certum', [
        'sources' => '',
        'token' => '',
        'id_field' => 'id',
        'allowed_ips' => '',
    ]);

    $this->postJson('/callback/certum', [])
        ->assertOk()
        ->assertJson(['code' => 0]);
});

// ==========================================
// sources 限定
// ==========================================

test('sources 限定只查指定来源的订单', function () {
    setupCallbackEndpoint('certum', [
        'sources' => 'certum,certumcnssl',
        'token' => '',
        'id_field' => 'id',
        'allowed_ips' => '',
    ]);

    $mockAction = Mockery::mock(Action::class);
    $mockAction->shouldReceive('createTask')->once();
    app()->instance(Action::class, $mockAction);

    createCallbackTestOrder(
        ['source' => 'certum'],
        ['api_id' => 'source-match-api', 'status' => 'processing'],
    );

    $this->postJson('/callback/certum', [
        'id' => 'source-match-api',
    ])->assertOk()
        ->assertJson(['code' => 1]);
});

test('其他来源的同 api_id 订单不匹配', function () {
    setupCallbackEndpoint('certum', [
        'sources' => 'certum,certumcnssl',
        'token' => '',
        'id_field' => 'id',
        'allowed_ips' => '',
    ]);

    createCallbackTestOrder(
        ['source' => 'gogetssl'],
        ['api_id' => 'wrong-source-api', 'status' => 'processing'],
    );

    $this->postJson('/callback/certum', [
        'id' => 'wrong-source-api',
    ])->assertOk()
        ->assertJson(['code' => 0]);
});

test('sources 为空时不限定来源全局查找', function () {
    setupCallbackEndpoint('default', [
        'sources' => '',
        'token' => '',
        'id_field' => 'id',
        'allowed_ips' => '',
    ]);

    $mockAction = Mockery::mock(Action::class);
    $mockAction->shouldReceive('createTask')->once();
    app()->instance(Action::class, $mockAction);

    createCallbackTestOrder(
        ['source' => 'gogetssl'],
        ['api_id' => 'any-source-api', 'status' => 'processing'],
    );

    $this->postJson('/callback/default', [
        'id' => 'any-source-api',
    ])->assertOk()
        ->assertJson(['code' => 1]);
});

// ==========================================
// 状态过滤
// ==========================================

test('processing 状态创建 sync 任务', function () {
    setupCallbackEndpoint('default', [
        'sources' => '',
        'token' => '',
        'id_field' => 'id',
        'allowed_ips' => '',
    ]);

    $mockAction = Mockery::mock(Action::class);
    $mockAction->shouldReceive('createTask')->with(Mockery::any(), 'sync')->once();
    app()->instance(Action::class, $mockAction);

    createCallbackTestOrder(
        [],
        ['api_id' => 'processing-api', 'status' => 'processing'],
    );

    $this->postJson('/callback/default', [
        'id' => 'processing-api',
    ])->assertOk()
        ->assertJson(['code' => 1]);
});

test('active 状态创建 sync 任务', function () {
    setupCallbackEndpoint('default', [
        'sources' => '',
        'token' => '',
        'id_field' => 'id',
        'allowed_ips' => '',
    ]);

    $mockAction = Mockery::mock(Action::class);
    $mockAction->shouldReceive('createTask')->with(Mockery::any(), 'sync')->once();
    app()->instance(Action::class, $mockAction);

    createCallbackTestOrder(
        [],
        ['api_id' => 'active-api', 'status' => 'active'],
    );

    $this->postJson('/callback/default', [
        'id' => 'active-api',
    ])->assertOk()
        ->assertJson(['code' => 1]);
});

test('approving 状态创建 sync 任务', function () {
    setupCallbackEndpoint('default', [
        'sources' => '',
        'token' => '',
        'id_field' => 'id',
        'allowed_ips' => '',
    ]);

    $mockAction = Mockery::mock(Action::class);
    $mockAction->shouldReceive('createTask')->with(Mockery::any(), 'sync')->once();
    app()->instance(Action::class, $mockAction);

    createCallbackTestOrder(
        [],
        ['api_id' => 'approving-api', 'status' => 'approving'],
    );

    $this->postJson('/callback/default', [
        'id' => 'approving-api',
    ])->assertOk()
        ->assertJson(['code' => 1]);
});

test('cancelled 状态不创建 sync 任务', function () {
    setupCallbackEndpoint('default', [
        'sources' => '',
        'token' => '',
        'id_field' => 'id',
        'allowed_ips' => '',
    ]);

    $mockAction = Mockery::mock(Action::class);
    $mockAction->shouldReceive('createTask')->never();
    app()->instance(Action::class, $mockAction);

    createCallbackTestOrder(
        [],
        ['api_id' => 'cancelled-api', 'status' => 'cancelled'],
    );

    $this->postJson('/callback/default', [
        'id' => 'cancelled-api',
    ])->assertOk()
        ->assertJson(['code' => 1]);
});
