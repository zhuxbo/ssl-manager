<?php

use App\Exceptions\ApiResponseException;
use App\Jobs\TaskJob;
use App\Models\Task;
use App\Services\Order\Action;
use App\Services\Order\Api\Api;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Traits\CreatesTestData;

uses(CreatesTestData::class);

function pendingReferRecoveryOrder(): array
{
    $user = test()->createTestUser();
    $product = test()->createTestProduct();
    $order = test()->createTestOrder($user, $product);
    $cert = test()->createTestCert($order, [
        'status' => 'pending', 'api_id' => null, 'refer_id' => 'stable-refer',
        'action' => 'new', 'amount' => '100.00',
    ]);

    return [$order, $cert];
}

function commitRecoveredOrder(Action $action, int $id): void
{
    try {
        $action->commit($id);
        test()->fail('提交必须返回成功响应');
    } catch (ApiResponseException $e) {
        expect($e->getApiResponse()['code'])->toBe(1);
    }
}

test('认领后才创建同步任务，正常提交不额外建任务', function (?bool $recovered) {
    Queue::fake();
    [$order, $cert] = pendingReferRecoveryOrder();
    $api = Mockery::mock(Api::class);
    $response = [
        'code' => 1,
        'data' => ['api_id' => 'existing-order', 'cert_apply_status' => 2],
    ];
    if ($recovered !== null) {
        $response['recovered'] = $recovered;
    }
    $api->shouldReceive('new')->once()->andReturn($response);
    app()->instance(Api::class, $api);
    $action = app(Action::class);
    commitRecoveredOrder($action, $order->id);

    expect($cert->fresh()->api_id)->toBe('existing-order')
        ->and($cert->fresh()->refer_id)->toBe('stable-refer')
        ->and($cert->fresh()->status)->toBe('processing')
        ->and(Task::where('order_id', $order->id)->where('action', 'sync')->count())->toBe($recovered ? 1 : 0);

    if ($recovered) {
        Queue::assertPushed(TaskJob::class);
        $api->shouldReceive('get')->once()->with($order->id)->andReturn([
            'code' => 1, 'data' => ['status' => 'active', 'cert_apply_status' => 2],
        ]);
        $action->sync($order->id, true);
        expect($cert->fresh()->status)->toBe('active');
    } else {
        Queue::assertNothingPushed();
    }
})->with([true, false, null]);

test('认领外层事务回滚时不创建同步任务', function () {
    Queue::fake();
    [$order, $cert] = pendingReferRecoveryOrder();
    $api = Mockery::mock(Api::class);
    $api->shouldReceive('new')->once()->andReturn([
        'code' => 1, 'recovered' => true, 'data' => ['api_id' => 'existing-order'],
    ]);
    app()->instance(Api::class, $api);
    DB::beginTransaction();
    try {
        commitRecoveredOrder(app(Action::class), $order->id);
        expect(Task::where('order_id', $order->id)->where('action', 'sync')->exists())->toBeFalse();
    } finally {
        DB::rollBack();
    }

    expect($cert->fresh()->api_id)->toBeNull()->and($cert->fresh()->status)->toBe('pending');
    Queue::assertNothingPushed();
});
