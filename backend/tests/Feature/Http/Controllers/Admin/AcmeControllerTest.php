<?php

use App\Exceptions\ApiResponseException;
use App\Models\Acme;
use App\Models\Admin;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use App\Services\Acme\Action;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(Tests\Traits\ActsAsAdmin::class);
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::factory()->create();
});

/**
 * 创建 ACME 产品及价格
 */
function createAcmeProduct(array $productOverrides = []): Product
{
    return Product::factory()->create(array_merge([
        'product_type' => Product::TYPE_ACME,
    ], $productOverrides));
}

/**
 * 创建产品价格
 */
function createProductPrice(Product $product, User $user, string $price = '100.00'): void
{
    ProductPrice::create([
        'product_id' => $product->id,
        'level_code' => $user->level_code ?? 'standard',
        'period' => 12,
        'price' => $price,
        'alternative_standard_price' => '10.00',
        'alternative_wildcard_price' => '20.00',
    ]);
}

/**
 * 通过 Action 创建 unpaid 的 ACME 订单
 */
function createAcmeViaAction(User $user, Product $product, array $overrides = []): Acme
{
    $action = app(Action::class);

    $params = array_merge([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'period' => 12,
        'purchased_standard_count' => 0,
        'purchased_wildcard_count' => 0,
    ], $overrides);

    try {
        $action->new($params);
        test()->fail('期望抛出 ApiResponseException 但未抛出');
    } catch (ApiResponseException $e) {
        $response = $e->getApiResponse();
        expect($response['code'])->toBe(1);

        return Acme::find($response['data']['order_id']);
    }
}

// ==================== index ====================

test('index 返回列表', function () {
    $acme1 = Acme::factory()->create();
    $acme2 = Acme::factory()->create();

    $response = $this->actingAsAdmin($this->admin)->getJson('/api/admin/acme/');

    $response->assertOk()->assertJson(['code' => 1]);
    $response->assertJsonStructure(['data' => ['items', 'total', 'pageSize', 'currentPage']]);
    expect($response->json('data.total'))->toBe(2);
    expect($response->json('data.items'))->toHaveCount(2);
});

test('index 按 user_id 过滤', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    Acme::factory()->create(['user_id' => $user1->id]);
    Acme::factory()->create(['user_id' => $user2->id]);

    $response = $this->actingAsAdmin($this->admin)->getJson("/api/admin/acme/?user_id=$user1->id");

    $response->assertOk()->assertJson(['code' => 1]);
    expect($response->json('data.total'))->toBe(1);
    expect($response->json('data.items.0.user_id'))->toBe($user1->id);
});

test('index 按 status 过滤', function () {
    Acme::factory()->active()->create();
    Acme::factory()->cancelled()->create();

    $response = $this->actingAsAdmin($this->admin)->getJson('/api/admin/acme/?status=active');

    $response->assertOk()->assertJson(['code' => 1]);
    expect($response->json('data.total'))->toBe(1);
    expect($response->json('data.items.0.status'))->toBe('active');
});

// ==================== show ====================

test('show 返回详情含 eab_hmac', function () {
    $acme = Acme::factory()->active()->create([
        'eab_kid' => 'test-kid',
        'eab_hmac' => 'test-hmac-secret',
    ]);

    $response = $this->actingAsAdmin($this->admin)->getJson("/api/admin/acme/$acme->id");

    $response->assertOk()->assertJson(['code' => 1]);
    $response->assertJsonPath('data.id', $acme->id);
    $response->assertJsonPath('data.eab_kid', 'test-kid');
    // eab_hmac 通过 makeVisible 暴露，应存在于响应中
    expect($response->json('data.eab_hmac'))->not->toBeNull();
});

test('show 不存在返回错误', function () {
    $response = $this->actingAsAdmin($this->admin)->getJson('/api/admin/acme/99999');

    $response->assertOk()->assertJson(['code' => 0]);
});

// ==================== new ====================

test('new 成功创建订单', function () {
    $user = User::factory()->create(['balance' => '500.00']);
    $product = createAcmeProduct();
    createProductPrice($product, $user);

    $response = $this->actingAsAdmin($this->admin)->postJson('/api/admin/acme/new', [
        'user_id' => $user->id,
        'product_id' => $product->id,
        'period' => 12,
        'purchased_standard_count' => 1,
        'purchased_wildcard_count' => 0,
    ]);

    $response->assertOk()->assertJson(['code' => 1]);
    expect($response->json('data.order_id'))->toBeGreaterThan(0);

    $acme = Acme::find($response->json('data.order_id'));
    expect($acme)->not->toBeNull();
    expect($acme->status)->toBe(Acme::STATUS_UNPAID);
    expect($acme->user_id)->toBe($user->id);
    expect($acme->product_id)->toBe($product->id);
});

// ==================== pay ====================

test('pay 成功支付', function () {
    $user = User::factory()->create(['balance' => '500.00']);
    $product = createAcmeProduct();
    createProductPrice($product, $user);

    $acme = createAcmeViaAction($user, $product);
    expect($acme->status)->toBe(Acme::STATUS_UNPAID);

    $response = $this->actingAsAdmin($this->admin)->postJson("/api/admin/acme/pay/$acme->id");

    $response->assertOk()->assertJson(['code' => 1]);

    $acme->refresh();
    expect($acme->status)->toBe(Acme::STATUS_PENDING);
});

// ==================== commit ====================

test('commit 成功提交', function () {
    $user = User::factory()->create(['balance' => '500.00']);
    $product = createAcmeProduct(['source' => 'certumcnssl']);
    createProductPrice($product, $user);

    $acme = createAcmeViaAction($user, $product);

    // 先支付
    $action = app(Action::class);
    try {
        $action->pay($acme->id);
    } catch (ApiResponseException) {
    }

    $acme->refresh();
    expect($acme->status)->toBe(Acme::STATUS_PENDING);

    // Mock Gateway HTTP
    config(['acme.api.base_url' => 'https://fake-gateway.test', 'acme.api.api_key' => 'fake-key']);
    Http::fake([
        'fake-gateway.test/*' => Http::response([
            'code' => 1,
            'data' => [
                'api_id' => 'gw-123',
                'vendor_id' => 'v-456',
                'eab_kid' => 'kid-abc',
                'eab_hmac' => 'hmac-xyz',
            ],
        ]),
    ]);

    $response = $this->actingAsAdmin($this->admin)->postJson("/api/admin/acme/commit/$acme->id");

    $response->assertOk()->assertJson(['code' => 1]);
    expect($response->json('data.eab_kid'))->toBe('kid-abc');
    expect($response->json('data.eab_hmac'))->toBe('hmac-xyz');

    $acme->refresh();
    expect($acme->status)->toBe(Acme::STATUS_ACTIVE);
    expect($acme->api_id)->toBe('gw-123');
});

// ==================== sync ====================

test('sync 成功同步', function () {
    $product = createAcmeProduct(['source' => 'certumcnssl']);
    $acme = Acme::factory()->active()->create([
        'product_id' => $product->id,
        'api_id' => 'gw-sync-test',
    ]);

    config(['acme.api.base_url' => 'https://fake-gateway.test', 'acme.api.api_key' => 'fake-key']);
    Http::fake([
        'fake-gateway.test/*' => Http::response([
            'code' => 1,
            'data' => ['status' => 'expired', 'vendor_id' => 'v-new'],
        ]),
    ]);

    $response = $this->actingAsAdmin($this->admin)->postJson("/api/admin/acme/sync/$acme->id");

    $response->assertOk()->assertJson(['code' => 1]);

    $acme->refresh();
    expect($acme->status)->toBe(Acme::STATUS_EXPIRED);
    expect($acme->vendor_id)->toBe('v-new');
});

// ==================== commitCancel ====================

test('commitCancel 成功取消', function () {
    Queue::fake();

    $product = createAcmeProduct();
    $acme = Acme::factory()->active()->create([
        'product_id' => $product->id,
        'api_id' => 'upstream-123',
        'amount' => '100.00',
    ]);

    $response = $this->actingAsAdmin($this->admin)->postJson("/api/admin/acme/commit-cancel/$acme->id");

    $response->assertOk()->assertJson(['code' => 1]);

    $acme->refresh();
    expect($acme->status)->toBe(Acme::STATUS_CANCELLING);
    expect($acme->cancelled_at)->not->toBeNull();
});

// ==================== remark ====================

test('remark 更新 admin_remark', function () {
    $acme = Acme::factory()->create();

    $response = $this->actingAsAdmin($this->admin)->postJson("/api/admin/acme/remark/$acme->id", [
        'remark' => '管理员测试备注',
    ]);

    $response->assertOk()->assertJson(['code' => 1]);

    $acme->refresh();
    expect($acme->admin_remark)->toBe('管理员测试备注');
});
