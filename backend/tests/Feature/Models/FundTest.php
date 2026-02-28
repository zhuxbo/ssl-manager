<?php

use App\Models\Fund;
use App\Models\User;

test('资金记录属于用户', function () {
    $user = User::factory()->create();
    $fund = Fund::factory()->create(['user_id' => $user->id]);

    expect($fund->user)->toBeInstanceOf(User::class);
    expect($fund->user->id)->toBe($user->id);
});

test('amount 为 decimal:2 格式', function () {
    $fund = Fund::factory()->create(['amount' => '123.456']);
    $fund->refresh();

    // Fund creating 事件会格式化 amount
    expect($fund->amount)->toMatch('/^\d+\.\d{2}$/');
});

test('status 为整数', function () {
    $fund = Fund::factory()->create(['status' => 0]);
    $fund->refresh();

    expect($fund->status)->toBeInt();
    expect($fund->status)->toBe(0);
});

test('pay_sn 为空字符串时存为 null', function () {
    $fund = Fund::factory()->create(['pay_sn' => '']);
    $fund->refresh();

    expect($fund->pay_sn)->toBeNull();
});

test('不允许创建已退状态的资金记录', function () {
    expect(fn () => Fund::factory()->create(['status' => 2]))
        ->toThrow(Exception::class, '已退状态不允许创建');
});

test('只读字段不可修改', function () {
    $fund = Fund::factory()->create();
    $originalAmount = $fund->amount;
    $originalUserId = $fund->user_id;

    $fund->amount = '999999.99';
    $fund->user_id = 99999;
    $fund->save();
    $fund->refresh();

    // 只读字段应保持原值
    expect($fund->user_id)->toBe($originalUserId);
});

test('用户有多个资金记录', function () {
    $user = User::factory()->create();
    Fund::factory()->count(3)->create(['user_id' => $user->id]);

    expect($user->funds)->toHaveCount(3);
});

test('完成状态的资金会创建交易记录', function () {
    $user = User::factory()->withBalance('1000.00')->create();
    $fund = Fund::factory()->create([
        'user_id' => $user->id,
        'amount' => '100.00',
        'type' => 'addfunds',
        'status' => 1,
    ]);

    expect($user->transactions()->where('transaction_id', $fund->id)->exists())->toBeTrue();
});
