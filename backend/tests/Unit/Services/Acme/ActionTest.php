<?php

use App\Exceptions\ApiResponseException;
use App\Jobs\TaskJob;
use App\Models\Acme;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Task;
use App\Models\Transaction;
use App\Services\Acme\Action;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

// ==================== new ====================

test('new creates unpaid order', function () {
    $user = $this->createTestUser(['balance' => '500.00']);
    $product = $this->createTestProduct(['product_type' => Product::TYPE_ACME]);
    createAcmeProductPrice($product->id, $user);

    $acme = $this->service->new($user, $product->id, 12, 1, 0, 'test remark');

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

    $acme1 = $this->service->new($user, $product->id, 12, 1, 0);
    $acme2 = $this->service->new($user, $product->id, 12, 1, 0);

    expect($acme1->refer_id)->not->toBe($acme2->refer_id);
});

test('new rejects non-acme product', function () {
    $user = $this->createTestUser(['balance' => '500.00']);
    $product = $this->createTestProduct(['product_type' => Product::TYPE_SSL]);

    expectApiError(
        fn () => $this->service->new($user, $product->id, 12, 1, 0),
        '产品不存在或不支持 ACME'
    );
});

test('new rejects invalid period', function () {
    $user = $this->createTestUser(['balance' => '500.00']);
    $product = $this->createTestProduct(['product_type' => Product::TYPE_ACME, 'periods' => [12, 24]]);
    createAcmeProductPrice($product->id, $user);

    expectApiError(
        fn () => $this->service->new($user, $product->id, 6, 1, 0),
        '无效的购买时长'
    );
});

// ==================== pay ====================

test('pay deducts balance and sets pending', function () {
    $user = $this->createTestUser(['balance' => '500.00']);
    $product = $this->createTestProduct(['product_type' => Product::TYPE_ACME]);
    createAcmeProductPrice($product->id, $user);

    $acme = $this->service->new($user, $product->id, 12, 1, 0);

    $initialBalance = (float) $user->balance;

    $result = $this->service->pay($acme);

    expect($result->status)->toBe(Acme::STATUS_PENDING);

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
        fn () => $this->service->pay($acme),
        '订单不是未支付状态'
    );
});

test('pay rejects when balance insufficient', function () {
    $user = $this->createTestUser(['balance' => '0.00']);
    $product = $this->createTestProduct(['product_type' => Product::TYPE_ACME]);
    createAcmeProductPrice($product->id, $user, '500.00');

    $acme = $this->service->new($user, $product->id, 12, 1, 0);

    expectApiError(
        fn () => $this->service->pay($acme),
        '余额不足'
    );
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

    $result = $this->service->commitCancel($acme);

    expect($result->status)->toBe(Acme::STATUS_CANCELLING);
    expect($result->cancelled_at)->not->toBeNull();

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

    $acme = $this->service->new($user, $product->id, 12, 1, 0);
    $this->service->pay($acme);
    $acme->refresh();

    expect($acme->status)->toBe(Acme::STATUS_PENDING);
    expect($acme->api_id)->toBeNull();

    $result = $this->service->commitCancel($acme);

    expect($result->status)->toBe(Acme::STATUS_CANCELLED);
    expect($result->cancelled_at)->not->toBeNull();
});

test('commitCancel rejects already cancelled order', function () {
    $user = $this->createTestUser(['balance' => '500.00']);
    $acme = Acme::factory()->cancelled()->create([
        'user_id' => $user->id,
    ]);

    expectApiError(
        fn () => $this->service->commitCancel($acme),
        '当前状态不允许取消'
    );
});

// ==================== cancel ====================

test('cancel cancels order without api_id', function () {
    $user = $this->createTestUser(['balance' => '500.00']);
    $product = $this->createTestProduct(['product_type' => Product::TYPE_ACME]);
    createAcmeProductPrice($product->id, $user);

    $acme = $this->service->new($user, $product->id, 12, 1, 0);
    $this->service->pay($acme);
    $acme->refresh();

    // 手动设为 cancelling
    $acme->update(['status' => Acme::STATUS_CANCELLING]);

    $result = $this->service->cancel($acme);

    expect($result['code'])->toBe(1);
    $acme->refresh();
    expect($acme->status)->toBe(Acme::STATUS_CANCELLED);
});

test('cancel rejects non-cancelling order', function () {
    $user = $this->createTestUser(['balance' => '500.00']);
    $acme = Acme::factory()->active()->create([
        'user_id' => $user->id,
    ]);

    $result = $this->service->cancel($acme);

    expect($result['code'])->toBe(0);
    expect($result['msg'])->toContain('取消中');
});

test('cancel with api_id upstream returns revoked → status revoked + refund', function () {
    $user = $this->createTestUser(['balance' => '500.00']);
    $product = $this->createTestProduct(['product_type' => Product::TYPE_ACME, 'source' => 'certumcnssl']);
    createAcmeProductPrice($product->id, $user);

    $acme = $this->service->new($user, $product->id, 12, 1, 0);
    $this->service->pay($acme);
    $acme->refresh();

    $acme->update([
        'status' => Acme::STATUS_CANCELLING,
        'api_id' => 'upstream-revoke-test',
    ]);

    config(['acme.api.base_url' => 'https://fake-gateway.test', 'acme.api.api_key' => 'fake-key']);
    Http::fake([
        'fake-gateway.test/*' => Http::response(['code' => 1, 'data' => ['status' => 'revoked']]),
    ]);

    $result = $this->service->cancel($acme);

    expect($result['code'])->toBe(1);
    $acme->refresh();
    expect($acme->status)->toBe(Acme::STATUS_REVOKED);

    $cancelTx = Transaction::where('transaction_id', $acme->id)
        ->where('type', Transaction::TYPE_ACME_CANCEL)
        ->first();
    expect($cancelTx)->not->toBeNull();
});

test('cancel with api_id upstream returns cancelled → status cancelled + refund', function () {
    $user = $this->createTestUser(['balance' => '500.00']);
    $product = $this->createTestProduct(['product_type' => Product::TYPE_ACME, 'source' => 'certumcnssl']);
    createAcmeProductPrice($product->id, $user);

    $acme = $this->service->new($user, $product->id, 12, 1, 0);
    $this->service->pay($acme);
    $acme->refresh();

    $acme->update([
        'status' => Acme::STATUS_CANCELLING,
        'api_id' => 'upstream-cancel-test',
    ]);

    config(['acme.api.base_url' => 'https://fake-gateway.test', 'acme.api.api_key' => 'fake-key']);
    Http::fake([
        'fake-gateway.test/*' => Http::response(['code' => 1, 'data' => ['status' => 'cancelled']]),
    ]);

    $result = $this->service->cancel($acme);

    expect($result['code'])->toBe(1);
    $acme->refresh();
    expect($acme->status)->toBe(Acme::STATUS_CANCELLED);

    $cancelTx = Transaction::where('transaction_id', $acme->id)
        ->where('type', Transaction::TYPE_ACME_CANCEL)
        ->first();
    expect($cancelTx)->not->toBeNull();
});

test('cancel with api_id upstream error → stays cancelling, no refund', function () {
    $user = $this->createTestUser(['balance' => '500.00']);
    $product = $this->createTestProduct(['product_type' => Product::TYPE_ACME, 'source' => 'certumcnssl']);
    createAcmeProductPrice($product->id, $user);

    $acme = $this->service->new($user, $product->id, 12, 1, 0);
    $this->service->pay($acme);
    $acme->refresh();

    $acme->update([
        'status' => Acme::STATUS_CANCELLING,
        'api_id' => 'upstream-error-test',
    ]);

    config(['acme.api.base_url' => 'https://fake-gateway.test', 'acme.api.api_key' => 'fake-key']);
    Http::fake([
        'fake-gateway.test/*' => Http::response(['code' => 0, 'msg' => '上游取消失败'], 500),
    ]);

    $result = $this->service->cancel($acme);

    expect($result['code'])->toBe(0);
    $acme->refresh();
    expect($acme->status)->toBe(Acme::STATUS_CANCELLING);

    $cancelTx = Transaction::where('transaction_id', $acme->id)
        ->where('type', Transaction::TYPE_ACME_CANCEL)
        ->first();
    expect($cancelTx)->toBeNull();
});
