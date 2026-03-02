<?php

use App\Models\Invoice;
use App\Models\User;

test('发票属于用户', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->create(['user_id' => $user->id]);

    expect($invoice->user)->toBeInstanceOf(User::class);
    expect($invoice->user->id)->toBe($user->id);
});

test('amount 为 decimal:2 格式', function () {
    $invoice = Invoice::factory()->create(['amount' => '500.00']);
    $invoice->refresh();

    expect($invoice->amount)->toMatch('/^\d+\.\d{2}$/');
});

test('status 为整数', function () {
    $invoice = Invoice::factory()->create(['status' => 0]);
    $invoice->refresh();

    expect($invoice->status)->toBeInt();
});

test('待开具状态为 0', function () {
    $invoice = Invoice::factory()->create();
    $invoice->refresh();

    expect($invoice->status)->toBe(0);
});

test('不允许创建已作废状态的发票', function () {
    $invoice = Invoice::factory()->create(['status' => 2]);

    // 由于 creating 事件 return false，模型不会被保存
    expect(Invoice::find($invoice->id))->toBeNull();
});

test('已开具状态不允许回退到待开具', function () {
    $invoice = Invoice::factory()->create(['status' => 1]);

    $invoice->status = 0;
    $invoice->save();
    $invoice->refresh();

    // updating 事件阻止状态从 1 回退到 0
    expect($invoice->status)->toBe(1);
});

test('已作废状态不允许更新', function () {
    // 先创建待开具发票，然后开具，再作废
    $invoice = Invoice::factory()->create(['status' => 0]);
    $invoice->status = 1;
    $invoice->save();
    $invoice->refresh();

    $invoice->status = 2;
    $invoice->save();
    $invoice->refresh();

    // 尝试再次修改
    $invoice->remark = 'updated';
    $invoice->save();
    $invoice->refresh();

    expect($invoice->status)->toBe(2);
});

test('只有待开具状态可以删除', function () {
    $invoicePending = Invoice::factory()->create(['status' => 0]);
    expect($invoicePending->delete())->toBeTrue();

    $invoiceIssued = Invoice::factory()->create(['status' => 1]);
    expect($invoiceIssued->delete())->toBeFalse();
});

test('只读字段不可修改', function () {
    $invoice = Invoice::factory()->create();
    $originalAmount = $invoice->amount;

    $invoice->amount = '999999.99';
    $invoice->save();
    $invoice->refresh();

    // amount 是只读字段，不应被修改
    expect($invoice->amount)->toBe($originalAmount);
});

test('用户有多个发票', function () {
    $user = User::factory()->create();
    Invoice::factory()->count(3)->create(['user_id' => $user->id]);

    expect($user->invoices)->toHaveCount(3);
});
