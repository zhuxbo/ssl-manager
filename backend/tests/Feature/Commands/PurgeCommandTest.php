<?php

use App\Models\AdminLog;
use App\Models\ApiLog;
use App\Models\CallbackLog;
use App\Models\CaLog;
use App\Models\ErrorLog;
use App\Models\Fund;
use App\Models\User;

test('签名为 schedule:purge', function () {
    $this->artisan('schedule:purge')->assertSuccessful();
});

test('清理超过24小时的未支付充值', function () {
    $user = User::factory()->create();

    // 超过24小时的未支付充值 - 直接插入避免触发模型事件
    Fund::unguard();
    $oldFund = Fund::create([
        'user_id' => $user->id,
        'amount' => '100.00',
        'type' => 'addfunds',
        'pay_method' => 'alipay',
        'ip' => '127.0.0.1',
        'status' => 0,
        'created_at' => now()->subHours(25),
    ]);
    Fund::reguard();

    // 新的未支付充值（不应被清理）
    $newFund = Fund::create([
        'user_id' => $user->id,
        'amount' => '200.00',
        'type' => 'addfunds',
        'pay_method' => 'alipay',
        'pay_sn' => 'PAY'.uniqid(),
        'ip' => '127.0.0.1',
        'status' => 0,
    ]);

    $this->artisan('schedule:purge')->assertSuccessful();

    expect(Fund::find($oldFund->id))->toBeNull();
    expect(Fund::find($newFund->id))->not->toBeNull();
});

test('命令输出包含清理统计', function () {
    $this->artisan('schedule:purge')
        ->expectsOutputToContain('Purged')
        ->assertSuccessful();
});
