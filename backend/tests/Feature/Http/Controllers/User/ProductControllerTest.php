<?php

use App\Models\Cert;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Tests\Traits\ActsAsUser;

uses(ActsAsUser::class);

test('获取产品列表-无需认证', function () {
    Product::factory()->count(3)->create();

    $this->getJson('/api/product')
        ->assertOk()
        ->assertJson(['code' => 1])
        ->assertJsonStructure(['data' => ['items', 'total', 'pageSize', 'currentPage']]);
});

test('获取产品列表-已登录时包含价格', function () {
    $user = User::factory()->create();
    Product::factory()->count(2)->create();

    $this->actingAsUser($user)
        ->getJson('/api/product')
        ->assertOk()
        ->assertJson(['code' => 1]);
});

test('获取产品列表-按品牌筛选', function () {
    Product::factory()->create(['brand' => 'BrandA']);
    Product::factory()->create(['brand' => 'BrandB']);

    $this->getJson('/api/product?brand=BrandA')
        ->assertOk()
        ->assertJson(['code' => 1]);
});

test('获取产品列表-按验证类型筛选', function () {
    Product::factory()->create(['validation_type' => 'dv']);
    Product::factory()->create(['validation_type' => 'ov']);

    $this->getJson('/api/product?validation_type=dv')
        ->assertOk()
        ->assertJson(['code' => 1]);
});

test('获取产品详情-无需认证', function () {
    $product = Product::factory()->create();

    $this->getJson("/api/product/$product->id")
        ->assertOk()
        ->assertJson(['code' => 1])
        ->assertJsonStructure(['data' => ['id', 'name', 'product_type', 'brand']]);
});

test('获取产品详情-产品不存在', function () {
    $this->getJson('/api/product/99999')
        ->assertOk()
        ->assertJson(['code' => 0]);
});

test('获取产品详情-已下架产品不可见', function () {
    $product = Product::factory()->create(['status' => 0]);

    $this->getJson("/api/product/$product->id")
        ->assertOk()
        ->assertJson(['code' => 0]);
});

test('获取产品详情-可重签活动订单可加载禁用产品配置但不进入产品列表', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['status' => 0, 'reissue' => 1]);
    $order = Order::factory()->create(['user_id' => $user->id, 'product_id' => $product->id]);
    $cert = Cert::factory()->active()->create(['order_id' => $order->id]);
    $order->update(['latest_cert_id' => $cert->id]);

    $response = $this->actingAsUser($user)
        ->getJson("/api/product/$product->id")
        ->assertOk()
        ->assertJsonPath('code', 1)
        ->assertJsonPath('data.id', $product->id)
        ->assertJsonPath('data.reissue', 1)
        ->assertJsonStructure(['data' => ['validation_methods', 'periods', 'reuse_csr', 'encryption_alg', 'price']]);

    expect($response->json('data'))->not->toHaveKeys(['api_id', 'source', 'cost']);

    $this->getJson('/api/product')
        ->assertJsonPath('code', 1)
        ->assertJsonPath('data.total', 0);
});

test('获取产品详情-不能查看他人已购的禁用产品', function () {
    $product = Product::factory()->create(['status' => 0, 'reissue' => 1]);
    $order = Order::factory()->create(['product_id' => $product->id]);
    $cert = Cert::factory()->active()->create(['order_id' => $order->id]);
    $order->update(['latest_cert_id' => $cert->id]);

    $this->actingAsUser(User::factory()->create())
        ->getJson("/api/product/$product->id")
        ->assertOk()
        ->assertJsonPath('code', 0);
});

test('获取产品详情-拥有其他订单不能查看未购禁用产品', function () {
    $user = User::factory()->create();
    Order::factory()->create(['user_id' => $user->id]);
    $product = Product::factory()->create(['status' => 0]);

    $this->actingAsUser($user)
        ->getJson("/api/product/$product->id")
        ->assertOk()
        ->assertJsonPath('code', 0);
});

test('获取产品详情-没有可重签活动订单时禁用产品不可见', function (?string $status, bool $expired, int $reissue) {
    $user = User::factory()->create();
    $product = Product::factory()->create(['status' => 0, 'reissue' => $reissue]);
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'period_till' => $expired ? now()->subDay() : now()->addYear(),
    ]);
    if ($status !== null) {
        // 历史活动证书不能替代最新证书的状态判断。
        Cert::factory()->active()->create(['order_id' => $order->id]);
        $cert = Cert::factory()->create(['order_id' => $order->id, 'status' => $status]);
        $order->update(['latest_cert_id' => $cert->id]);
    }

    $this->actingAsUser($user)
        ->getJson("/api/product/$product->id")
        ->assertOk()
        ->assertJsonPath('code', 0);
})->with([
    '无最新证书' => [null, false, 1],
    '未支付' => ['unpaid', false, 1],
    '待提交' => ['pending', false, 1],
    '待验证' => ['processing', false, 1],
    '签发中' => ['approving', false, 1],
    '取消中' => ['cancelling', false, 1],
    '已取消' => ['cancelled', false, 1],
    '已过期' => ['expired', false, 1],
    '已吊销' => ['revoked', false, 1],
    '已续费' => ['renewed', false, 1],
    '已重签' => ['reissued', false, 1],
    '已归档' => ['archived', false, 1],
    '活动证书但订单已到期' => ['active', true, 1],
    '产品不支持重签' => ['active', false, 0],
]);

test('获取产品列表-只展示上架产品', function () {
    Product::factory()->create(['status' => 1]);
    Product::factory()->create(['status' => 0]);

    $response = $this->getJson('/api/product')
        ->assertOk()
        ->assertJson(['code' => 1]);

    expect($response->json('data.total'))->toBe(1);
});

test('获取产品列表-默认排除 ACME 产品', function () {
    $sslProduct = Product::factory()->create(['product_type' => Product::TYPE_SSL]);
    Product::factory()->create(['product_type' => Product::TYPE_ACME]);

    $response = $this->getJson('/api/product')
        ->assertOk()
        ->assertJson(['code' => 1]);

    expect($response->json('data.total'))->toBe(1)
        ->and($response->json('data.items'))->toHaveCount(1)
        ->and($response->json('data.items.0.id'))->toBe($sslProduct->id);
});

test('获取产品列表-明确筛选 ACME 时展示 ACME 产品', function () {
    Product::factory()->create(['product_type' => Product::TYPE_SSL]);
    $acmeProduct = Product::factory()->create(['product_type' => Product::TYPE_ACME]);

    $response = $this->getJson('/api/product?product_type=acme')
        ->assertOk()
        ->assertJson(['code' => 1]);

    expect($response->json('data.total'))->toBe(1)
        ->and($response->json('data.items'))->toHaveCount(1)
        ->and($response->json('data.items.0.id'))->toBe($acmeProduct->id);
});
