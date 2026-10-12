<?php

use App\Models\ApiToken;
use App\Models\Cert;
use App\Models\Order;
use App\Services\Order\Action;
use App\Services\Order\Api\Api;
use Illuminate\Support\Facades\Cache;
use Tests\Traits\ActsAsAdmin;
use Tests\Traits\ActsAsUser;
use Tests\Traits\CreatesTestData;

uses(CreatesTestData::class, ActsAsUser::class, ActsAsAdmin::class);

test('同步将审核意见保存到订单并兼容清空及旧上游', function (array $payload, ?string $expected) {
    $user = $this->createTestUser();
    $product = $this->createTestProduct();
    $order = $this->createTestOrder($user, $product, ['review_note' => '旧意见']);
    $this->createTestCert($order, ['status' => 'processing', 'api_id' => 'upstream-id']);
    $mock = Mockery::mock(Api::class);
    $mock->shouldReceive('get')->once()->andReturn(['code' => 1, 'data' => $payload]);
    app()->instance(Api::class, $mock);

    app(Action::class)->sync($order->id, true);
    expect($order->fresh()->review_note)->toBe($expected);
    Cache::put('api_get_'.$order->id, time(), 120);
    $token = ApiToken::createToken($user->id);
    $this->withHeaders(['Authorization' => 'Bearer '.$token])
        ->getJson('/api/v2/get?order_id='.$order->id)
        ->assertJsonPath('code', 1)->assertJsonStructure(['data' => ['review_note']])
        ->assertJsonPath('data.review_note', $expected);
})->with([
    [['review_note' => "请补充授权书。\n请上传清晰执照。"], "请补充授权书。\n请上传清晰执照。"],
    [['review_note' => null], null],
    [[], '旧意见'],
]);

test('旧申请同步不能覆盖新一轮订单审核意见', function () {
    $user = $this->createTestUser();
    $product = $this->createTestProduct();
    $order = $this->createTestOrder($user, $product, ['review_note' => '旧意见']);
    $cert = $this->createTestCert($order, ['status' => 'processing', 'api_id' => 'upstream-id']);
    $mock = Mockery::mock(Api::class);
    $mock->shouldReceive('get')->once()->andReturnUsing(function () use ($order, $cert) {
        $next = Cert::factory()->create(['order_id' => $order->id, 'status' => 'pending']);
        $cert->update(['status' => 'reissued']);
        $order->update(['latest_cert_id' => $next->id, 'review_note' => '新申请意见']);

        return ['code' => 1, 'data' => ['review_note' => '迟到的旧意见']];
    });
    app()->instance(Api::class, $mock);
    app(Action::class)->sync($order->id, true);
    expect($order->fresh()->review_note)->toBe('新申请意见');
});

test('管理端和用户端详情返回订单审核意见', function (string $role) {
    $user = $this->createTestUser();
    $product = $this->createTestProduct();
    $order = $this->createTestOrder($user, $product, ['review_note' => '请补充授权书。']);
    $this->createTestCert($order, ['status' => 'processing', 'api_id' => 'upstream-id']);
    if ($role === 'admin') {
        $this->actingAsAdmin();
    } else {
        $this->actingAsUser($user);
    }
    $this->getJson(($role === 'admin' ? '/api/admin/order/' : '/api/order/').$order->id)
        ->assertJsonPath('code', 1)->assertJsonPath('data.review_note', '请补充授权书。');
})->with(['admin', 'user']);

test('同步按锁内订单值更新意见并兼容并发写入后的清空', function (array $payload, ?string $expected) {
    $user = $this->createTestUser();
    $product = $this->createTestProduct();
    $order = $this->createTestOrder($user, $product, ['review_note' => null]);
    $this->createTestCert($order, ['status' => 'processing', 'api_id' => 'upstream-id']);
    $mock = Mockery::mock(Api::class);
    $mock->shouldReceive('get')->once()->andReturnUsing(function () use ($order, $payload) {
        // 模拟锁外上游请求期间，另一同步已写入意见。
        Order::findOrFail($order->id)->update(['review_note' => '并发写入的旧意见']);

        return ['code' => 1, 'data' => $payload];
    });
    app()->instance(Api::class, $mock);
    app(Action::class)->sync($order->id, true);

    expect($order->fresh()->review_note)->toBe($expected);
    if (isset($payload['cert_apply_status'])) {
        expect($order->fresh()->latestCert->cert_apply_status)->toBe(2);
    }
})->with([
    '提交后清空' => [['review_note' => null, 'cert_apply_status' => 2], null],
    '新意见覆盖' => [['review_note' => '最新意见'], '最新意见'],
    '旧上游缺字段保留' => [[], '并发写入的旧意见'],
]);
