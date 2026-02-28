<?php

use App\Models\CnameDelegation;
use App\Models\User;
use App\Services\Delegation\CnameDelegationService;

uses(Tests\Traits\ActsAsUser::class, Tests\Traits\MocksExternalApis::class);

test('获取委托列表', function () {
    $user = User::factory()->create();
    CnameDelegation::factory()->count(3)->create(['user_id' => $user->id]);

    $this->actingAsUser($user)
        ->getJson('/api/delegation')
        ->assertOk()
        ->assertJson(['code' => 1])
        ->assertJsonStructure(['data' => ['items', 'total', 'pageSize', 'currentPage']]);
});

test('获取委托列表-快速搜索', function () {
    $user = User::factory()->create();
    CnameDelegation::factory()->create([
        'user_id' => $user->id,
        'zone' => 'searchme.com',
    ]);

    $this->actingAsUser($user)
        ->getJson('/api/delegation?quickSearch=searchme')
        ->assertOk()
        ->assertJson(['code' => 1]);
});

test('创建委托', function () {
    $user = User::factory()->create();

    $mockService = Mockery::mock(CnameDelegationService::class);
    $delegation = CnameDelegation::factory()->create(['user_id' => $user->id]);
    $mockService->shouldReceive('createOrGet')->once()->andReturn($delegation);
    $mockService->shouldReceive('withCnameGuide')->once()->andReturn($delegation->toArray());
    app()->instance(CnameDelegationService::class, $mockService);

    $this->actingAsUser($user)
        ->postJson('/api/delegation', [
            'zone' => 'example.com',
            'prefix' => '_acme-challenge',
        ])
        ->assertOk()
        ->assertJson(['code' => 1]);
});

test('获取委托详情', function () {
    $user = User::factory()->create();
    $delegation = CnameDelegation::factory()->create(['user_id' => $user->id]);

    $this->actingAsUser($user)
        ->getJson("/api/delegation/$delegation->id")
        ->assertOk()
        ->assertJson(['code' => 1]);
});

test('获取委托详情-不存在', function () {
    $user = User::factory()->create();

    $this->actingAsUser($user)
        ->getJson('/api/delegation/99999')
        ->assertOk()
        ->assertJson(['code' => 0]);
});

test('删除委托', function () {
    $user = User::factory()->create();
    $delegation = CnameDelegation::factory()->create(['user_id' => $user->id]);

    $this->actingAsUser($user)
        ->deleteJson("/api/delegation/$delegation->id")
        ->assertOk()
        ->assertJson(['code' => 1]);

    expect(CnameDelegation::find($delegation->id))->toBeNull();
});

test('批量创建委托', function () {
    $user = User::factory()->create();

    $mockService = Mockery::mock(CnameDelegationService::class);
    $delegation = CnameDelegation::factory()->create(['user_id' => $user->id]);
    $mockService->shouldReceive('createOrGet')->andReturn($delegation);
    $mockService->shouldReceive('withCnameGuide')->andReturn($delegation->toArray());
    app()->instance(CnameDelegationService::class, $mockService);

    $this->actingAsUser($user)
        ->postJson('/api/delegation/batch-store', [
            'zones' => "example1.com\nexample2.com",
            'prefix' => '_acme-challenge',
        ])
        ->assertOk()
        ->assertJson(['code' => 1])
        ->assertJsonStructure(['data' => ['created', 'failed', 'total', 'success_count', 'fail_count']]);
});

test('手动检查委托', function () {
    $user = User::factory()->create();
    $delegation = CnameDelegation::factory()->create(['user_id' => $user->id]);

    $mockService = Mockery::mock(CnameDelegationService::class);
    $mockService->shouldReceive('checkAndUpdateValidity')->once()->andReturn(true);
    $mockService->shouldReceive('checkTxtConflict')->once()->andReturn(null);
    $mockService->shouldReceive('withCnameGuide')->once()->andReturn($delegation->toArray());
    app()->instance(CnameDelegationService::class, $mockService);

    $this->actingAsUser($user)
        ->postJson("/api/delegation/check/$delegation->id")
        ->assertOk()
        ->assertJson(['code' => 1]);
});

test('批量删除委托', function () {
    $user = User::factory()->create();
    $delegations = CnameDelegation::factory()->count(3)->create(['user_id' => $user->id]);
    $ids = $delegations->pluck('id')->toArray();

    $this->actingAsUser($user)
        ->deleteJson('/api/delegation/batch', ['ids' => $ids])
        ->assertOk()
        ->assertJson(['code' => 1]);

    expect(CnameDelegation::whereIn('id', $ids)->count())->toBe(0);
});

test('委托列表-未认证', function () {
    $this->getJson('/api/delegation')
        ->assertUnauthorized();
});
