<?php

use App\Exceptions\ApiResponseException;
use App\Models\Task;
use App\Models\Transaction;
use App\Services\Order\Action;
use App\Services\Order\Api\Api;
use App\Services\Order\StalledRenewalQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Traits\ActsAsAdmin;
use Tests\Traits\ActsAsUser;
use Tests\Traits\CreatesTestData;

uses(ActsAsAdmin::class, ActsAsUser::class, CreatesTestData::class);

beforeEach(function () {
    $user = $this->createTestUser();
    $product = $this->createTestProduct();
    $order = $this->createTestOrder($user, $product, [
        'period_till' => now()->addDays(200), 'auto_renew' => true, 'auto_reissue' => true,
    ]);
    $cert = $this->createTestCert($order, ['status' => 'active', 'api_id' => 'archive-test']);

    $this->archiveFixture = [$user, $order, $cert];
});

test('归档两端真实入口：处理中和已签发可归档，不受续费窗口限制且清理任务不动资金', function (string $role, string $status) {
    [$user, $order, $cert] = $this->archiveFixture;
    $cert->update(['status' => $status]);
    $balance = $user->balance;
    $transactions = Transaction::count();
    foreach (['sync', 'revalidate', 'commit', 'cancel'] as $action) {
        Task::create(['order_id' => $order->id, 'action' => $action, 'status' => 'executing']);
    }
    $role === 'admin' ? $this->actingAsAdmin() : $this->actingAsUser($user);
    $prefix = $role === 'admin' ? '/api/admin' : '/api';
    $this->postJson("$prefix/order/archive/$order->id")->assertOk()->assertJsonPath('code', 1);
    expect($cert->fresh()->status)->toBe('archived')
        ->and($order->fresh()->auto_renew)->toBeFalse()
        ->and($order->fresh()->auto_reissue)->toBeFalse()
        ->and(Task::where('order_id', $order->id)->count())->toBe(0)
        ->and($user->fresh()->balance)->toBe($balance)
        ->and(Transaction::count())->toBe($transactions);
    $this->getJson("$prefix/order?status=archived&statusSet=archived")->assertOk()->assertJsonPath('code', 1);
})->with(['admin', 'user'])->with(['processing', 'active']);

test('归档拒绝处理中和已签发以外的状态', function (string $status) {
    [$user, $order, $cert] = $this->archiveFixture;
    $cert->update(['status' => $status]);
    $this->actingAsUser($user)->postJson("/api/order/archive/$order->id")
        ->assertOk()->assertJsonPath('code', 0);
    expect($cert->fresh()->status)->toBe($status);
})->with(['unpaid', 'pending', 'approving', 'cancelling', 'cancelled', 'expired', 'renewed', 'reissued', 'revoked', 'archived']);

test('归档不允许访问他人订单，旧已续及撤回入口已关闭', function () {
    [$owner, $order, $cert] = $this->archiveFixture;
    $this->actingAsUser($this->createTestUser());
    $this->postJson("/api/order/archive/$order->id")->assertOk()->assertJsonPath('code', 0);
    expect($cert->fresh()->status)->toBe('active');
    $this->actingAsUser($owner);
    foreach (["mark-renewed/$order->id", "revoke-cancel/$order->id", 'batch-revoke-cancel'] as $route) {
        $this->postJson("/api/order/$route")->assertNotFound();
    }
});

test('同步在途期间归档不被上游 active 覆盖，归档不作为续期停滞', function (string $status) {
    [$user, $order, $cert] = $this->archiveFixture;
    $cert->update(['status' => $status]);
    $api = Mockery::mock(Api::class);
    $api->shouldReceive('get')->once()->andReturnUsing(function () use ($order) {
        try {
            app(Action::class)->archive($order->id);
        } catch (ApiResponseException $e) {
            expect($e->getApiResponse()['code'])->toBe(1);
        }

        return ['code' => 1, 'data' => ['status' => 'active']];
    });
    $this->app->instance(Api::class, $api);
    app(Action::class)->sync($order->id, true);
    expect($cert->fresh()->status)->toBe('archived')
        ->and(StalledRenewalQuery::SUCCESSOR_STALLED_STATUSES)->not->toContain('archived');
})->with(['processing', 'active']);

test('取消提交即创建可立即执行的任务，保留取消中状态和失败证据', function () {
    Queue::fake();
    [$user, $order, $cert] = $this->archiveFixture;
    $this->actingAsUser($user)->postJson("/api/order/commit-cancel/$order->id")
        ->assertOk()->assertJsonPath('code', 1);
    $task = Task::where('order_id', $order->id)->where('action', 'cancel')->sole();
    expect($task->started_at->lte(now()))->toBeTrue()
        ->and($cert->fresh()->status)->toBe('cancelling');
    // 测试事务未提交，afterCommit 的任务尚未派发；持久化的 started_at 已可执行。
    $api = Mockery::mock(Api::class);
    $api->shouldReceive('cancel')->once()->andThrow(new RuntimeException('upstream unavailable'));
    $this->app->instance(Api::class, $api);
    expect(fn () => app(Action::class)->cancel($order->id))->toThrow(RuntimeException::class);
    expect($cert->fresh()->status)->toBe('cancelling')
        ->and(Task::find($task->id))->not->toBeNull();
});

test('同步将旧上游 failed 映射为归档且保留已有资金', function () {
    [$user, $order, $cert] = $this->archiveFixture;
    $cert->update(['status' => 'processing']);
    $balance = $user->balance;
    $transactions = Transaction::count();
    $api = Mockery::mock(Api::class);
    $api->shouldReceive('get')->once()->andReturn([
        'code' => 1, 'data' => ['status' => 'failed'],
    ]);
    $this->app->instance(Api::class, $api);

    app(Action::class)->sync($order->id, true);

    expect($cert->fresh()->status)->toBe('archived')
        ->and($user->fresh()->balance)->toBe($balance)
        ->and(Transaction::count())->toBe($transactions);
});

test('归档先锁定全部待清理任务再锁订单', function () {
    [, $order] = $this->archiveFixture;
    foreach (['commit', 'sync', 'revalidate', 'cancel'] as $action) {
        Task::create(['order_id' => $order->id, 'action' => $action, 'status' => 'executing']);
    }
    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
        try {
            app(Action::class)->archive($order->id);
            $this->fail('归档应返回成功响应');
        } catch (ApiResponseException $e) {
            expect($e->getApiResponse()['code'])->toBe(1);
        }
        $locks = collect(DB::getQueryLog())
            ->filter(fn ($query) => str_contains(strtolower($query['query']), 'for update'))
            ->values();
        // 删除前必须先覆盖整组 task 锁，避免任务执行与归档形成反向锁序。
        expect($locks)->toHaveCount(2)
            ->and($locks[0]['query'])->toContain('`tasks`', 'tasks_order_action_status_index')
            ->and($locks[0]['bindings'])->toContain($order->id, 'commit', 'sync', 'revalidate', 'cancel')
            ->and($locks[1]['query'])->toContain('`orders`');
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
});

test('待审核订单提交取消也立即创建任务', function () {
    Queue::fake();
    [, $order, $cert] = $this->archiveFixture;
    $cert->update(['status' => 'approving']);
    try {
        app(Action::class)->commitCancel($order->id);
        $this->fail('取消应返回成功响应');
    } catch (ApiResponseException $e) {
        expect($e->getApiResponse()['code'])->toBe(1);
    }

    $task = Task::where('order_id', $order->id)->where('action', 'cancel')->sole();
    expect($cert->fresh()->status)->toBe('cancelling')
        ->and($task->started_at->lte(now()))->toBeTrue();
});
