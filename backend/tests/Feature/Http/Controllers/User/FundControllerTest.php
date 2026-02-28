<?php

use App\Models\Fund;
use App\Models\User;

uses(Tests\Traits\ActsAsUser::class);

test('获取资金记录列表', function () {
    $user = User::factory()->create();
    Fund::factory()->count(3)->completed()->create(['user_id' => $user->id]);

    $this->actingAsUser($user)
        ->getJson('/api/fund')
        ->assertOk()
        ->assertJson(['code' => 1])
        ->assertJsonStructure(['data' => ['items', 'total', 'pageSize', 'currentPage']]);
});

test('获取资金记录列表-按类型筛选', function () {
    $user = User::factory()->create();
    Fund::factory()->completed()->create([
        'user_id' => $user->id,
        'type' => 'addfunds',
    ]);
    Fund::factory()->completed()->create([
        'user_id' => $user->id,
        'type' => 'deduct',
    ]);

    $this->actingAsUser($user)
        ->getJson('/api/fund?type=addfunds')
        ->assertOk()
        ->assertJson(['code' => 1]);
});

test('获取资金记录列表-按状态筛选', function () {
    $user = User::factory()->create();
    Fund::factory()->completed()->create(['user_id' => $user->id]);

    $this->actingAsUser($user)
        ->getJson('/api/fund?status=1')
        ->assertOk()
        ->assertJson(['code' => 1]);
});

test('获取资金记录列表-按支付方式筛选', function () {
    $user = User::factory()->create();
    Fund::factory()->completed()->create([
        'user_id' => $user->id,
        'pay_method' => 'alipay',
    ]);

    $this->actingAsUser($user)
        ->getJson('/api/fund?pay_method=alipay')
        ->assertOk()
        ->assertJson(['code' => 1]);
});

test('获取资金记录列表-不显示 status=0 记录', function () {
    $user = User::factory()->create();
    Fund::factory()->create([
        'user_id' => $user->id,
        'status' => 0,
    ]);

    $response = $this->actingAsUser($user)
        ->getJson('/api/fund')
        ->assertOk()
        ->assertJson(['code' => 1]);

    expect($response->json('data.total'))->toBe(0);
});

test('检查充值状态-无效ID', function () {
    $user = User::factory()->create();

    $this->actingAsUser($user)
        ->postJson('/api/fund/check/99999')
        ->assertOk()
        ->assertJson(['code' => 0]);
});

test('资金记录-未认证', function () {
    $this->getJson('/api/fund')
        ->assertUnauthorized();
});
