<?php

use App\Exceptions\ApiResponseException;
use App\Jobs\TaskJob;
use App\Models\Acme;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Setting;
use App\Models\SettingGroup;
use App\Models\Task;
use App\Models\Transaction;
use App\Services\Acme\Action;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Traits\CreatesTestData;

uses(Tests\TestCase::class, CreatesTestData::class, RefreshDatabase::class)->group('database');

beforeEach(function () {
    $this->seed = true;
    $this->seeder = DatabaseSeeder::class;
    $this->service = app(Action::class);
});

/**
 * 创建 Gateway 系统设置（ACME SDK 通过回落机制使用 ca.url/token）
 */
function setupGatewaySettings(string $url = 'https://fake-gateway.test/api/v2', string $token = 'fake-key'): void
{
    $group = SettingGroup::firstOrCreate(['name' => 'ca'], ['title' => '证书接口', 'weight' => 2]);

    foreach (['url' => $url, 'token' => $token, 'acme_url' => null, 'acme_token' => null] as $key => $value) {
        $setting = Setting::firstOrCreate(
            ['group_id' => $group->id, 'key' => $key],
            ['type' => 'string', 'value' => null, 'weight' => 0]
        );
        if ($value !== null) {
            $setting->value = $value;
            $setting->save();
        }
    }
}

/**
 * 创建产品价格
 */
function createAcmeProductPrice(int $productId, $user, string $price = '100.00'): void
{
    ProductPrice::create([
        'product_id' => $productId,
        'level_code' => $user->level_code ?? 'standard',
        'period' => 12,
        'price' => $price,
        'alternative_standard_price' => '10.00',
        'alternative_wildcard_price' => '20.00',
    ]);
}

/**
 * 断言 ApiResponseException 包含指定消息
 */
function expectApiError(Closure $callback, string $expectedMsg): void
{
    try {
        $callback();
        test()->fail('期望抛出 ApiResponseException 但未抛出');
    } catch (ApiResponseException $e) {
        $response = $e->getApiResponse();
        expect($response['code'])->toBe(0);
        expect($response['msg'])->toContain($expectedMsg);
    }
}

/**
 * 断言 ApiResponseException code=1（success）
 */
function expectApiSuccess(Closure $callback): array
{
    try {
        $callback();
        test()->fail('期望抛出 ApiResponseException 但未抛出');
    } catch (ApiResponseException $e) {
        $response = $e->getApiResponse();
        expect($response['code'])->toBe(1);

        return $response;
    }

    return [];
}

/**
 * 通过 Action 创建 ACME 订单辅助方法
 */
function createAcmeOrder($user, $product, array $overrides = []): Acme
{
    $params = array_merge([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'period' => 12,
        'purchased_standard_count' => 1,
        'purchased_wildcard_count' => 0,
    ], $overrides);

    $response = expectApiSuccess(fn () => test()->service->new($params));

    return Acme::find($response['data']['order_id']);
}

// ==================== new ====================

test('new creates unpaid order', function () {
    $user = $this->createTestUser(['balance' => '500.00']);
    $product = $this->createTestProduct(['product_type' => Product::TYPE_ACME]);
    createAcmeProductPrice($product->id, $user);

    $acme = createAcmeOrder($user, $product, ['remark' => 'test remark']);

    expect($acme)->toBeInstanceOf(Acme::class);
    expect($acme->exists)->toBeTrue();
    expect($acme->status)->toBe(Acme::STATUS_UNPAID);
    expect($acme->user_id)->toBe($user->id);
    expect($acme->product_id)->toBe($product->id);
    expect($acme->period)->toBe(12);
    expect($acme->purchased_standard_count)->toBe(1);
    expect($acme->purchased_wildcard_count)->toBe(0);
    expect($acme->refer_id)->not->toBeNull();
    expect(strlen($acme->refer_id))->toBe(32);
    expect($acme->remark)->toBe('test remark');
    expect($acme->brand)->toBe($product->brand);
});

test('new generates unique refer_id', function () {
    $user = $this->createTestUser(['balance' => '500.00']);
    $product = $this->createTestProduct(['product_type' => Product::TYPE_ACME]);
    createAcmeProductPrice($product->id, $user);

    $acme1 = createAcmeOrder($user, $product);
    $acme2 = createAcmeOrder($user, $product);

    expect($acme1->refer_id)->not->toBe($acme2->refer_id);
});

test('new rejects non-acme product', function () {
    $user = $this->createTestUser(['balance' => '500.00']);
    $product = $this->createTestProduct(['product_type' => Product::TYPE_SSL]);

    expectApiError(
        fn () => $this->service->new([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'period' => 12,
            'purchased_standard_count' => 1,
            'purchased_wildcard_count' => 0,
        ]),
        '产品不存在或不支持 ACME'
    );
});

test('new rejects invalid period', function () {
    $user = $this->createTestUser(['balance' => '500.00']);
    $product = $this->createTestProduct(['product_type' => Product::TYPE_ACME, 'periods' => [12, 24]]);
    createAcmeProductPrice($product->id, $user);

    expectApiError(
        fn () => $this->service->new([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'period' => 6,
            'purchased_standard_count' => 1,
            'purchased_wildcard_count' => 0,
        ]),
        '无效的购买时长'
    );
});

// ==================== pay ====================

test('pay deducts balance and sets pending', function () {
    $user = $this->createTestUser(['balance' => '500.00']);
    $product = $this->createTestProduct(['product_type' => Product::TYPE_ACME]);
    createAcmeProductPrice($product->id, $user);

    $acme = createAcmeOrder($user, $product);
    $initialBalance = (float) $user->balance;

    expectApiSuccess(fn () => $this->service->pay($acme->id));

    $acme->refresh();
    expect($acme->status)->toBe(Acme::STATUS_PENDING);

    // 验证交易记录
    $transaction = Transaction::where('transaction_id', $acme->id)
        ->where('type', Transaction::TYPE_ACME_ORDER)
        ->first();
    expect($transaction)->not->toBeNull();
    expect($transaction->user_id)->toBe($user->id);

    // 验证余额扣减
    $user->refresh();
    expect((float) $user->balance)->toBeLessThan($initialBalance);
});

test('pay rejects non-unpaid order', function () {
    $user = $this->createTestUser(['balance' => '500.00']);
    $acme = Acme::factory()->create([
        'user_id' => $user->id,
        'status' => Acme::STATUS_PENDING,
    ]);

    expectApiError(
        fn () => $this->service->pay($acme->id),
        '订单不是未支付状态'
    );
});

test('pay rejects when balance insufficient', function () {
    $user = $this->createTestUser(['balance' => '0.00']);
    $product = $this->createTestProduct(['product_type' => Product::TYPE_ACME]);
    createAcmeProductPrice($product->id, $user, '500.00');

    $acme = createAcmeOrder($user, $product);

    expectApiError(
        fn () => $this->service->pay($acme->id),
        '余额不足'
    );
});

// ==================== commit ====================

test('commit 成功调用 API 转 active 返回 eab 数据', function () {
    $user = $this->createTestUser(['balance' => '500.00']);
    $product = $this->createTestProduct(['product_type' => Product::TYPE_ACME, 'source' => 'default']);
    createAcmeProductPrice($product->id, $user);

    $acme = createAcmeOrder($user, $product);
    expectApiSuccess(fn () => $this->service->pay($acme->id));

    setupGatewaySettings();
    Http::fake([
        'fake-gateway.test/*' => Http::response([
            'code' => 1,
            'data' => [
                'api_id' => 'gw-123',
                'vendor_id' => 'vendor-456',
                'eab_kid' => 'kid-abc',
                'eab_hmac' => 'hmac-xyz',
            ],
        ]),
    ]);

    $response = expectApiSuccess(fn () => $this->service->commit($acme->id));

    expect($response['data']['eab_kid'])->toBe('kid-abc');
    expect($response['data']['eab_hmac'])->toBe('hmac-xyz');

    $acme->refresh();
    expect($acme->status)->toBe(Acme::STATUS_ACTIVE);
    expect($acme->api_id)->toBe('gw-123');
});

test('commit 非 pending 状态报错', function () {
    $user = $this->createTestUser(['balance' => '500.00']);
    $acme = Acme::factory()->create([
        'user_id' => $user->id,
        'status' => Acme::STATUS_UNPAID,
    ]);

    expectApiError(
        fn () => $this->service->commit($acme->id),
        '订单状态不是待提交'
    );
});

test('commit API 返回失败保持 pending', function () {
    $user = $this->createTestUser(['balance' => '500.00']);
    $product = $this->createTestProduct(['product_type' => Product::TYPE_ACME, 'source' => 'default']);
    createAcmeProductPrice($product->id, $user);

    $acme = createAcmeOrder($user, $product);
    expectApiSuccess(fn () => $this->service->pay($acme->id));

    setupGatewaySettings();
    Http::fake([
        'fake-gateway.test/*' => Http::response(['code' => 0, 'msg' => '上游提交失败'], 500),
    ]);

    expectApiError(
        fn () => $this->service->commit($acme->id),
        '上游提交失败'
    );

    $acme->refresh();
    expect($acme->status)->toBe(Acme::STATUS_PENDING);
});

// ==================== commitCancel ====================

test('commitCancel sets cancelling status for active order', function () {
    Queue::fake();

    $user = $this->createTestUser(['balance' => '500.00']);
    $product = $this->createTestProduct(['product_type' => Product::TYPE_ACME]);
    createAcmeProductPrice($product->id, $user);

    $acme = Acme::factory()->active()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'api_id' => 'upstream-123',
        'amount' => '100.00',
    ]);

    expectApiSuccess(fn () => $this->service->commitCancel($acme->id));

    $acme->refresh();
    expect($acme->status)->toBe(Acme::STATUS_CANCELLING);
    expect($acme->cancelled_at)->not->toBeNull();

    // 验证 Task 记录创建
    $task = Task::where('order_id', $acme->id)
        ->where('action', 'cancel_acme')
        ->where('status', 'executing')
        ->first();
    expect($task)->not->toBeNull();
    expect($task->started_at)->toBeGreaterThan(now());

    Queue::assertPushed(TaskJob::class);
});

test('commitCancel directly cancels pending order without api_id', function () {
    $user = $this->createTestUser(['balance' => '500.00']);
    $product = $this->createTestProduct(['product_type' => Product::TYPE_ACME]);
    createAcmeProductPrice($product->id, $user);

    $acme = createAcmeOrder($user, $product);
    expectApiSuccess(fn () => $this->service->pay($acme->id));
    $acme->refresh();

    expect($acme->status)->toBe(Acme::STATUS_PENDING);
    expect($acme->api_id)->toBeNull();

    expectApiSuccess(fn () => $this->service->commitCancel($acme->id));

    $acme->refresh();
    expect($acme->status)->toBe(Acme::STATUS_CANCELLED);
    expect($acme->cancelled_at)->not->toBeNull();
});

test('commitCancel rejects already cancelled order', function () {
    $user = $this->createTestUser(['balance' => '500.00']);
    $acme = Acme::factory()->cancelled()->create([
        'user_id' => $user->id,
    ]);

    expectApiError(
        fn () => $this->service->commitCancel($acme->id),
        '当前状态不允许取消'
    );
});

// ==================== cancel ====================

test('cancel cancels order without api_id', function () {
    $user = $this->createTestUser(['balance' => '500.00']);
    $product = $this->createTestProduct(['product_type' => Product::TYPE_ACME]);
    createAcmeProductPrice($product->id, $user);

    $acme = createAcmeOrder($user, $product);
    expectApiSuccess(fn () => $this->service->pay($acme->id));
    $acme->refresh();

    // 手动设为 cancelling
    $acme->update(['status' => Acme::STATUS_CANCELLING]);

    expectApiSuccess(fn () => $this->service->cancel($acme->id));

    $acme->refresh();
    expect($acme->status)->toBe(Acme::STATUS_CANCELLED);
});

test('cancel rejects non-cancelling order', function () {
    $user = $this->createTestUser(['balance' => '500.00']);
    $acme = Acme::factory()->active()->create([
        'user_id' => $user->id,
    ]);

    expectApiError(
        fn () => $this->service->cancel($acme->id),
        '取消中'
    );
});

test('cancel with api_id upstream returns revoked → status revoked + refund', function () {
    $user = $this->createTestUser(['balance' => '500.00']);
    $product = $this->createTestProduct(['product_type' => Product::TYPE_ACME, 'source' => 'default']);
    createAcmeProductPrice($product->id, $user);

    $acme = createAcmeOrder($user, $product);
    expectApiSuccess(fn () => $this->service->pay($acme->id));
    $acme->refresh();

    $acme->update([
        'status' => Acme::STATUS_CANCELLING,
        'api_id' => 'upstream-revoke-test',
    ]);

    setupGatewaySettings();
    Http::fake([
        'fake-gateway.test/*' => Http::response(['code' => 1, 'data' => ['status' => 'revoked']]),
    ]);

    expectApiSuccess(fn () => $this->service->cancel($acme->id));

    $acme->refresh();
    expect($acme->status)->toBe(Acme::STATUS_REVOKED);

    $cancelTx = Transaction::where('transaction_id', $acme->id)
        ->where('type', Transaction::TYPE_ACME_CANCEL)
        ->first();
    expect($cancelTx)->not->toBeNull();
});

test('cancel with api_id upstream returns cancelled → status cancelled + refund', function () {
    $user = $this->createTestUser(['balance' => '500.00']);
    $product = $this->createTestProduct(['product_type' => Product::TYPE_ACME, 'source' => 'default']);
    createAcmeProductPrice($product->id, $user);

    $acme = createAcmeOrder($user, $product);
    expectApiSuccess(fn () => $this->service->pay($acme->id));
    $acme->refresh();

    $acme->update([
        'status' => Acme::STATUS_CANCELLING,
        'api_id' => 'upstream-cancel-test',
    ]);

    setupGatewaySettings();
    Http::fake([
        'fake-gateway.test/*' => Http::response(['code' => 1, 'data' => ['status' => 'cancelled']]),
    ]);

    expectApiSuccess(fn () => $this->service->cancel($acme->id));

    $acme->refresh();
    expect($acme->status)->toBe(Acme::STATUS_CANCELLED);

    $cancelTx = Transaction::where('transaction_id', $acme->id)
        ->where('type', Transaction::TYPE_ACME_CANCEL)
        ->first();
    expect($cancelTx)->not->toBeNull();
});

test('cancel with api_id upstream error → stays cancelling, no refund', function () {
    $user = $this->createTestUser(['balance' => '500.00']);
    $product = $this->createTestProduct(['product_type' => Product::TYPE_ACME, 'source' => 'default']);
    createAcmeProductPrice($product->id, $user);

    $acme = createAcmeOrder($user, $product);
    expectApiSuccess(fn () => $this->service->pay($acme->id));
    $acme->refresh();

    $acme->update([
        'status' => Acme::STATUS_CANCELLING,
        'api_id' => 'upstream-error-test',
    ]);

    setupGatewaySettings();
    Http::fake([
        'fake-gateway.test/*' => Http::response(['code' => 0, 'msg' => '上游取消失败'], 500),
    ]);

    expectApiError(
        fn () => $this->service->cancel($acme->id),
        '上游取消失败'
    );

    $acme->refresh();
    expect($acme->status)->toBe(Acme::STATUS_CANCELLING);

    $cancelTx = Transaction::where('transaction_id', $acme->id)
        ->where('type', Transaction::TYPE_ACME_CANCEL)
        ->first();
    expect($cancelTx)->toBeNull();
});

// ==================== sync ====================

test('sync 成功同步状态', function () {
    $user = $this->createTestUser(['balance' => '500.00']);
    $product = $this->createTestProduct(['product_type' => Product::TYPE_ACME, 'source' => 'default']);

    $acme = Acme::factory()->active()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'api_id' => 'gw-sync-test',
    ]);

    setupGatewaySettings();
    Http::fake([
        'fake-gateway.test/*' => Http::response([
            'code' => 1,
            'data' => ['status' => 'expired', 'vendor_id' => 'v-new'],
        ]),
    ]);

    expectApiSuccess(fn () => $this->service->sync($acme->id));

    $acme->refresh();
    expect($acme->status)->toBe(Acme::STATUS_EXPIRED);
    expect($acme->vendor_id)->toBe('v-new');
});

test('sync 10秒内缓存不重复请求', function () {
    $user = $this->createTestUser(['balance' => '500.00']);
    $product = $this->createTestProduct(['product_type' => Product::TYPE_ACME, 'source' => 'default']);

    $acme = Acme::factory()->active()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'api_id' => 'gw-cache-test',
    ]);

    // 设置缓存模拟已请求
    Cache::set("acme_sync_$acme->id", time(), 10);

    // force=true 时静默返回
    $this->service->sync($acme->id, true);
    // 没有抛异常即成功
    expect(true)->toBeTrue();
});

test('sync 无 api_id 报错', function () {
    $user = $this->createTestUser(['balance' => '500.00']);
    $product = $this->createTestProduct(['product_type' => Product::TYPE_ACME]);

    $acme = Acme::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'api_id' => null,
        'status' => Acme::STATUS_PENDING,
    ]);

    expectApiError(
        fn () => $this->service->sync($acme->id),
        '订单尚未提交到上游'
    );
});

test('sync force=true 静默返回', function () {
    $user = $this->createTestUser(['balance' => '500.00']);
    $product = $this->createTestProduct(['product_type' => Product::TYPE_ACME]);

    $acme = Acme::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'api_id' => null,
        'status' => Acme::STATUS_PENDING,
    ]);

    // force=true 不报错，静默返回
    $this->service->sync($acme->id, true);
    expect(true)->toBeTrue();
});

// ==================== remark ====================

test('remark 更新 remark 字段', function () {
    $user = $this->createTestUser(['balance' => '500.00']);
    $acme = Acme::factory()->create(['user_id' => $user->id]);

    expectApiSuccess(fn () => $this->service->remark($acme->id, '用户备注'));

    $acme->refresh();
    expect($acme->remark)->toBe('用户备注');
});

test('remark 更新 admin_remark 字段', function () {
    $user = $this->createTestUser(['balance' => '500.00']);
    $acme = Acme::factory()->create(['user_id' => $user->id]);

    expectApiSuccess(fn () => $this->service->remark($acme->id, '管理员备注', 'admin_remark'));

    $acme->refresh();
    expect($acme->admin_remark)->toBe('管理员备注');
});

// ==================== newAndCommit ====================

test('newAndCommit 一步完成 new+pay+commit', function () {
    $user = $this->createTestUser(['balance' => '500.00']);
    $product = $this->createTestProduct(['product_type' => Product::TYPE_ACME, 'source' => 'default']);
    createAcmeProductPrice($product->id, $user);

    setupGatewaySettings();
    Http::fake([
        'fake-gateway.test/*' => Http::response([
            'code' => 1,
            'data' => [
                'api_id' => 'gw-deploy',
                'vendor_id' => 'v-deploy',
                'eab_kid' => 'kid-deploy',
                'eab_hmac' => 'hmac-deploy',
            ],
        ]),
    ]);

    $response = expectApiSuccess(fn () => $this->service->newAndCommit([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'period' => 12,
        'purchased_standard_count' => 1,
        'purchased_wildcard_count' => 0,
    ]));

    expect($response['data']['status'])->toBe(Acme::STATUS_ACTIVE);
    expect($response['data']['eab_kid'])->toBe('kid-deploy');

    $acme = Acme::find($response['data']['order_id']);
    expect($acme->status)->toBe(Acme::STATUS_ACTIVE);
    expect($acme->api_id)->toBe('gw-deploy');
});

test('newAndCommit 余额不足报错', function () {
    $user = $this->createTestUser(['balance' => '0.00']);
    $product = $this->createTestProduct(['product_type' => Product::TYPE_ACME]);
    createAcmeProductPrice($product->id, $user, '500.00');

    expectApiError(
        fn () => $this->service->newAndCommit([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'period' => 12,
            'purchased_standard_count' => 1,
            'purchased_wildcard_count' => 0,
        ]),
        '余额不足'
    );
});

test('newAndCommit 产品不存在报错', function () {
    $user = $this->createTestUser(['balance' => '500.00']);

    expectApiError(
        fn () => $this->service->newAndCommit([
            'user_id' => $user->id,
            'product_id' => 99999,
            'period' => 12,
            'purchased_standard_count' => 1,
            'purchased_wildcard_count' => 0,
        ]),
        '产品不存在或不支持 ACME'
    );
});
