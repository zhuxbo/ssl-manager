<?php

use App\Models\Fund;
use App\Models\Setting;
use App\Models\SettingGroup;
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

    $response = $this->actingAsUser($user)
        ->getJson('/api/top-up/check/99999')
        ->assertOk()
        ->assertJson(['code' => 1]);

    $response->assertJsonPath('data.message', 'successful');
});

test('检查充值状态-已完成订单返回 successful', function () {
    $user = User::factory()->create();
    $fund = Fund::factory()->completed()->create([
        'user_id' => $user->id,
        'type' => 'addfunds',
        'pay_method' => 'alipay',
    ]);

    $response = $this->actingAsUser($user)
        ->getJson("/api/top-up/check/$fund->id")
        ->assertOk()
        ->assertJson(['code' => 1]);

    $response->assertJsonPath('data.message', 'successful');
    expect($fund->fresh()->status)->toBe(1);
});

test('检查充值状态-无权访问他人订单返回 successful', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $fund = Fund::factory()->create([
        'user_id' => $owner->id,
        'type' => 'addfunds',
        'pay_method' => 'wechat',
        'status' => 0,
    ]);

    $response = $this->actingAsUser($other)
        ->getJson("/api/top-up/check/$fund->id")
        ->assertOk()
        ->assertJson(['code' => 1]);

    $response->assertJsonPath('data.message', 'successful');
    expect($fund->fresh()->status)->toBe(0);
});

test('获取银行账户信息-未配置', function () {
    $user = User::factory()->create();

    $this->actingAsUser($user)
        ->getJson('/api/top-up/get-bank-account')
        ->assertOk()
        ->assertJson(['code' => 0]);
});

test('获取银行账户信息-已配置返回成功', function () {
    $user = User::factory()->create();

    $group = SettingGroup::firstOrCreate(
        ['name' => 'bankAccount'],
        ['title' => 'Bank Account', 'weight' => 0]
    );

    Setting::updateOrCreate(
        ['group_id' => $group->id, 'key' => 'bank_name'],
        ['type' => 'string', 'value' => 'Test Bank']
    );
    Setting::updateOrCreate(
        ['group_id' => $group->id, 'key' => 'account_name'],
        ['type' => 'string', 'value' => 'Test User']
    );
    Setting::updateOrCreate(
        ['group_id' => $group->id, 'key' => 'account_no'],
        ['type' => 'string', 'value' => '1234567890']
    );

    $response = $this->actingAsUser($user)
        ->getJson('/api/top-up/get-bank-account')
        ->assertOk()
        ->assertJson(['code' => 1]);

    $response->assertJsonPath('data.bank_name', 'Test Bank');
    $response->assertJsonPath('data.account_name', 'Test User');
    $response->assertJsonPath('data.account_no', '1234567890');
});

test('充值-未认证', function () {
    $this->postJson('/api/top-up/alipay', ['amount' => 100])
        ->assertUnauthorized();
});
