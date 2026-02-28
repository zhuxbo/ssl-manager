<?php

use App\Models\Fund;
use App\Models\Setting;
use App\Models\User;
use Yansongda\Pay\Pay;

uses(Tests\Traits\ActsAsUser::class);

test('支付宝充值-金额无效', function () {
    $user = User::factory()->create();

    $this->actingAsUser($user)
        ->postJson('/api/top-up/alipay', [
            'amount' => 0,
        ])
        ->assertOk()
        ->assertJson(['code' => 0]);
});

test('支付宝充值-金额为负数', function () {
    $user = User::factory()->create();

    $this->actingAsUser($user)
        ->postJson('/api/top-up/alipay', [
            'amount' => -100,
        ])
        ->assertOk()
        ->assertJson(['code' => 0]);
});

test('微信充值-金额无效', function () {
    $user = User::factory()->create();

    $this->actingAsUser($user)
        ->postJson('/api/top-up/wechat', [
            'amount' => 0,
        ])
        ->assertOk()
        ->assertJson(['code' => 0]);
});

test('检查充值状态-订单不存在返回成功', function () {
    $user = User::factory()->create();

    $this->actingAsUser($user)
        ->getJson('/api/top-up/check/99999')
        ->assertOk()
        ->assertJson(['code' => 1]);
});

test('获取银行账户信息-未配置', function () {
    $user = User::factory()->create();

    $this->actingAsUser($user)
        ->getJson('/api/top-up/get-bank-account')
        ->assertOk()
        ->assertJson(['code' => 0]);
});

test('充值-未认证', function () {
    $this->postJson('/api/top-up/alipay', ['amount' => 100])
        ->assertUnauthorized();
});
