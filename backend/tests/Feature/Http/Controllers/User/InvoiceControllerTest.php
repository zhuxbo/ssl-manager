<?php

use App\Models\Invoice;
use App\Models\User;

uses(Tests\Traits\ActsAsUser::class);

test('获取发票列表', function () {
    $user = User::factory()->create();
    Invoice::factory()->count(3)->create(['user_id' => $user->id]);

    $this->actingAsUser($user)
        ->getJson('/api/invoice')
        ->assertOk()
        ->assertJson(['code' => 1])
        ->assertJsonStructure(['data' => ['items', 'total', 'pageSize', 'currentPage']]);
});

test('获取发票列表-快速搜索', function () {
    $user = User::factory()->create();
    Invoice::factory()->create([
        'user_id' => $user->id,
        'organization' => 'SearchOrg',
    ]);

    $this->actingAsUser($user)
        ->getJson('/api/invoice?quickSearch=SearchOrg')
        ->assertOk()
        ->assertJson(['code' => 1]);
});

test('获取发票列表-按状态筛选', function () {
    $user = User::factory()->create();
    Invoice::factory()->create([
        'user_id' => $user->id,
        'status' => 0,
    ]);

    $this->actingAsUser($user)
        ->getJson('/api/invoice?status=0')
        ->assertOk()
        ->assertJson(['code' => 1]);
});

test('创建发票', function () {
    $user = User::factory()->create(['invoice_limit' => '10000.00']);

    $this->actingAsUser($user)
        ->postJson('/api/invoice', [
            'amount' => 1000,
            'organization' => 'Test Company',
            'taxation' => '1234567890',
            'email' => 'invoice@example.com',
        ])
        ->assertOk()
        ->assertJson(['code' => 1]);

    expect(Invoice::where('user_id', $user->id)->count())->toBe(1);
});

test('创建发票-超过额度', function () {
    $user = User::factory()->create(['invoice_limit' => '100.00']);

    $this->actingAsUser($user)
        ->postJson('/api/invoice', [
            'amount' => 1000,
            'organization' => 'Test Company',
            'taxation' => '1234567890',
            'email' => 'invoice@example.com',
        ])
        ->assertOk()
        ->assertJson(['code' => 0]);
});

test('获取发票详情', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->create(['user_id' => $user->id]);

    $this->actingAsUser($user)
        ->getJson("/api/invoice/$invoice->id")
        ->assertOk()
        ->assertJson(['code' => 1])
        ->assertJsonStructure(['data' => ['id', 'amount', 'organization', 'status']]);
});

test('获取发票详情-不存在', function () {
    $user = User::factory()->create();

    $this->actingAsUser($user)
        ->getJson('/api/invoice/99999')
        ->assertOk()
        ->assertJson(['code' => 0]);
});

test('更新发票', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->create(['user_id' => $user->id]);

    $this->actingAsUser($user)
        ->putJson("/api/invoice/$invoice->id", [
            'remark' => '已开具发票',
        ])
        ->assertOk()
        ->assertJson(['code' => 1]);

    expect($invoice->fresh()->remark)->toBe('已开具发票');
});

test('删除发票', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->create(['user_id' => $user->id]);

    $this->actingAsUser($user)
        ->deleteJson("/api/invoice/$invoice->id")
        ->assertOk()
        ->assertJson(['code' => 1]);

    expect(Invoice::find($invoice->id))->toBeNull();
});

test('批量获取发票', function () {
    $user = User::factory()->create();
    $invoices = Invoice::factory()->count(3)->create(['user_id' => $user->id]);
    $ids = $invoices->pluck('id')->toArray();

    $this->actingAsUser($user)
        ->getJson('/api/invoice/batch?ids='.implode(',', $ids))
        ->assertOk()
        ->assertJson(['code' => 1]);
});

test('批量删除发票', function () {
    $user = User::factory()->create();
    $invoices = Invoice::factory()->count(3)->create(['user_id' => $user->id]);
    $ids = $invoices->pluck('id')->toArray();

    $this->actingAsUser($user)
        ->deleteJson('/api/invoice/batch', ['ids' => $ids])
        ->assertOk()
        ->assertJson(['code' => 1]);

    expect(Invoice::whereIn('id', $ids)->count())->toBe(0);
});

test('发票列表-未认证', function () {
    $this->getJson('/api/invoice')
        ->assertUnauthorized();
});
