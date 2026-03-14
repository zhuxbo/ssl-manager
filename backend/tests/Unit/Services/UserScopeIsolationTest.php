<?php

use App\Exceptions\ApiResponseException;
use App\Models\Acme;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Scopes\UserScope;
use App\Models\Task;
use App\Services\Acme\Action as AcmeAction;
use App\Services\Order\Action as OrderAction;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Traits\CreatesTestData;

uses(Tests\TestCase::class, CreatesTestData::class, RefreshDatabase::class)->group('database');

beforeEach(function () {
    $this->seed = true;
    $this->seeder = DatabaseSeeder::class;
    $this->acmeAction = app(AcmeAction::class);
    $this->orderAction = app(OrderAction::class);
});

afterEach(function () {
    // UserScope 通过 Model::addGlobalScope 添加是静态的，测试间会残留，需要手动清理
    Acme::clearBootedModels();
    Order::clearBootedModels();
});

function createAcmeWithPrice(string $price = '100.00'): array
{
    $t = test();
    $user = $t->createTestUser(['balance' => '500.00']);
    $product = $t->createTestProduct(['product_type' => Product::TYPE_ACME]);
    ProductPrice::create([
        'product_id' => $product->id,
        'level_code' => $user->level_code ?? 'standard',
        'period' => 12,
        'price' => $price,
        'alternative_standard_price' => '10.00',
        'alternative_wildcard_price' => '20.00',
    ]);

    return [$user, $product];
}

function createPaidAcme($user, $product): Acme
{
    $action = test()->acmeAction;

    // 创建订单
    try {
        $action->new([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'period' => 12,
            'purchased_standard_count' => 1,
            'purchased_wildcard_count' => 0,
        ]);
    } catch (ApiResponseException $e) {
        expect($e->getApiResponse()['code'])->toBe(1);
        $acmeId = $e->getApiResponse()['data']['order_id'];
    }

    // 支付订单
    try {
        $action->pay($acmeId);
    } catch (ApiResponseException $e) {
        expect($e->getApiResponse()['code'])->toBe(1);
    }

    return Acme::findOrFail($acmeId);
}

// ==================== Acme\Action + UserScope 隔离 ====================

test('UserScope 注册后 Acme\Action::pay 查不到其他用户的订单', function () {
    [$userA, $product] = createAcmeWithPrice();
    $userB = $this->createTestUser(['balance' => '500.00']);

    // 用 userA 创建 unpaid 订单
    $acme = Acme::factory()->unpaid()->create([
        'user_id' => $userA->id,
        'product_id' => $product->id,
        'amount' => '100.00',
    ]);

    // 注册 userB 的 UserScope
    UserScope::addScopeToModels($userB->id, [Acme::class]);

    // userB 无法通过 findOrFail 找到 userA 的订单
    expect(fn () => $this->acmeAction->pay($acme->id))
        ->toThrow(ModelNotFoundException::class);
});

test('UserScope 注册后 Acme\Action::commit 查不到其他用户的订单', function () {
    [$userA, $product] = createAcmeWithPrice();
    $userB = $this->createTestUser(['balance' => '500.00']);

    $acme = createPaidAcme($userA, $product);
    expect($acme->status)->toBe(Acme::STATUS_PENDING);

    // 注册 userB 的 UserScope
    UserScope::addScopeToModels($userB->id, [Acme::class]);

    expect(fn () => $this->acmeAction->commit($acme->id))
        ->toThrow(ModelNotFoundException::class);
});

test('UserScope 注册后 Acme\Action::commitCancel 查不到其他用户的订单', function () {
    Queue::fake();

    [$userA, $product] = createAcmeWithPrice();
    $userB = $this->createTestUser(['balance' => '500.00']);

    $acme = Acme::factory()->active()->create([
        'user_id' => $userA->id,
        'product_id' => $product->id,
        'api_id' => 'upstream-123',
        'amount' => '100.00',
    ]);

    // 注册 userB 的 UserScope
    UserScope::addScopeToModels($userB->id, [Acme::class]);

    expect(fn () => $this->acmeAction->commitCancel($acme->id))
        ->toThrow(ModelNotFoundException::class);
});

test('UserScope 注册后 Acme\Action::sync 查不到其他用户的订单', function () {
    [$userA, $product] = createAcmeWithPrice();
    $userB = $this->createTestUser(['balance' => '500.00']);

    $acme = Acme::factory()->active()->create([
        'user_id' => $userA->id,
        'product_id' => $product->id,
        'api_id' => 'gw-sync-test',
    ]);

    // 注册 userB 的 UserScope
    UserScope::addScopeToModels($userB->id, [Acme::class]);

    // sync 使用 Acme::find，找不到时会进入 error 分支（非 ModelNotFoundException）
    try {
        $this->acmeAction->sync($acme->id);
        $this->fail('期望抛出异常');
    } catch (ApiResponseException $e) {
        expect($e->getApiResponse()['msg'])->toContain('订单不存在');
    }
});

test('UserScope 注册后 Acme\Action::remark 查不到其他用户的订单', function () {
    $userA = $this->createTestUser(['balance' => '500.00']);
    $userB = $this->createTestUser(['balance' => '500.00']);

    $acme = Acme::factory()->create(['user_id' => $userA->id]);

    // 注册 userB 的 UserScope
    UserScope::addScopeToModels($userB->id, [Acme::class]);

    expect(fn () => $this->acmeAction->remark($acme->id, '备注'))
        ->toThrow(ModelNotFoundException::class);
});

// ==================== 无 UserScope（Admin 场景） ====================

test('无 UserScope 时 Acme\Action::pay 可以访问任何用户的订单', function () {
    [$userA, $product] = createAcmeWithPrice();

    // 用 userA 创建 unpaid 订单
    $acme = Acme::factory()->unpaid()->create([
        'user_id' => $userA->id,
        'product_id' => $product->id,
        'amount' => '100.00',
    ]);

    // 不注册 UserScope，直接操作
    try {
        $this->acmeAction->pay($acme->id);
    } catch (ApiResponseException $e) {
        expect($e->getApiResponse()['code'])->toBe(1);
    }

    $acme->refresh();
    expect($acme->status)->toBe(Acme::STATUS_PENDING);
});

test('无 UserScope 时 Acme\Action::commitCancel 可以访问任何用户的订单', function () {
    Queue::fake();

    [$userA, $product] = createAcmeWithPrice();

    $acme = Acme::factory()->active()->create([
        'user_id' => $userA->id,
        'product_id' => $product->id,
        'api_id' => 'upstream-admin-cancel',
        'amount' => '100.00',
    ]);

    // 不注册 UserScope，Admin 可操作任何订单
    try {
        $this->acmeAction->commitCancel($acme->id);
    } catch (ApiResponseException $e) {
        expect($e->getApiResponse()['code'])->toBe(1);
    }

    $acme->refresh();
    expect($acme->status)->toBe(Acme::STATUS_CANCELLING);
});

// ==================== Order\Action + UserScope 隔离 ====================

test('UserScope 注册后 Order\Action::initParams renew 查不到其他用户的订单', function () {
    $userA = $this->createTestUser(['balance' => '500.00']);
    $userB = $this->createTestUser(['balance' => '500.00']);
    $product = $this->createTestProduct([
        'product_type' => Product::TYPE_SSL,
        'renew' => 1,
        'reissue' => 1,
    ]);

    // 为 userA 创建订单
    $order = $this->createTestOrder($userA, $product, [
        'period_from' => now()->subMonths(11),
        'period_till' => now()->addDays(15),
    ]);
    $this->createTestCert($order, ['status' => 'active']);

    // 注册 userB 的 UserScope
    UserScope::addScopeToModels($userB->id, [Order::class]);

    // userB 尝试续费 userA 的订单，initParams 中 Order::find 会被 UserScope 过滤
    try {
        $this->orderAction->renew([
            'order_id' => $order->id,
            'action' => 'renew',
            'domains' => 'example.com',
            'validation_method' => 'txt',
            'csr_generate' => 1,
        ]);
        $this->fail('期望抛出 ApiResponseException');
    } catch (ApiResponseException $e) {
        expect($e->getApiResponse()['msg'])->toContain('订单或相关数据不存在');
    }
});

test('UserScope 注册后 Order\Action::initParams reissue 查不到其他用户的订单', function () {
    $userA = $this->createTestUser(['balance' => '500.00']);
    $userB = $this->createTestUser(['balance' => '500.00']);
    $product = $this->createTestProduct([
        'product_type' => Product::TYPE_SSL,
        'reissue' => 1,
    ]);

    // 为 userA 创建订单
    $order = $this->createTestOrder($userA, $product, [
        'period_from' => now()->subMonths(1),
        'period_till' => now()->addMonths(11),
    ]);
    $this->createTestCert($order, ['status' => 'active']);

    // 注册 userB 的 UserScope
    UserScope::addScopeToModels($userB->id, [Order::class]);

    // userB 尝试重签 userA 的订单
    try {
        $this->orderAction->reissue([
            'order_id' => $order->id,
            'action' => 'reissue',
            'domains' => 'example.com',
            'validation_method' => 'txt',
            'csr_generate' => 1,
        ]);
        $this->fail('期望抛出 ApiResponseException');
    } catch (ApiResponseException $e) {
        expect($e->getApiResponse()['msg'])->toContain('订单或相关数据不存在');
    }
});

// ==================== createTask user_id 来源 ====================

test('Acme commitCancel 创建的 Task 记录包含正确的 user_id', function () {
    Queue::fake();

    [$user, $product] = createAcmeWithPrice();

    $acme = Acme::factory()->active()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'api_id' => 'upstream-task-test',
        'amount' => '100.00',
    ]);

    try {
        $this->acmeAction->commitCancel($acme->id);
    } catch (ApiResponseException $e) {
        expect($e->getApiResponse()['code'])->toBe(1);
    }

    $task = Task::where('order_id', $acme->id)
        ->where('action', 'cancel_acme')
        ->first();

    expect($task)->not->toBeNull();
    expect($task->user_id)->toBe($user->id);
});
