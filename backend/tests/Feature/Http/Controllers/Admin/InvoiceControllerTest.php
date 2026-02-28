<?php

use App\Models\Admin;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\Traits\ActsAsAdmin::class);
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::factory()->create();
    $this->user = User::factory()->create();
});

test('管理员可以获取发票列表', function () {
    Invoice::factory()->count(3)->create(['user_id' => $this->user->id]);

    $response = $this->actingAsAdmin($this->admin)->getJson('/api/admin/invoice');

    $response->assertOk()->assertJson(['code' => 1]);
    $response->assertJsonStructure(['data' => ['items', 'total', 'pageSize', 'currentPage']]);
});

test('管理员可以快速搜索发票', function () {
    Invoice::factory()->create(['user_id' => $this->user->id, 'organization' => 'Special Corp']);
    Invoice::factory()->create(['user_id' => $this->user->id, 'organization' => 'Other LLC']);

    $response = $this->actingAsAdmin($this->admin)->getJson('/api/admin/invoice?quickSearch=Special');

    $response->assertOk()->assertJson(['code' => 1]);
    expect($response->json('data.total'))->toBe(1);
});

test('管理员可以按状态筛选发票', function () {
    Invoice::factory()->create(['user_id' => $this->user->id, 'status' => 0]);
    Invoice::factory()->issued()->create(['user_id' => $this->user->id]);

    $response = $this->actingAsAdmin($this->admin)->getJson('/api/admin/invoice?status=1');

    $response->assertOk()->assertJson(['code' => 1]);
    expect($response->json('data.total'))->toBe(1);
});

test('管理员可以按组织名称搜索发票', function () {
    Invoice::factory()->create(['user_id' => $this->user->id, 'organization' => 'Target Company']);
    Invoice::factory()->create(['user_id' => $this->user->id, 'organization' => 'Other']);

    $response = $this->actingAsAdmin($this->admin)->getJson('/api/admin/invoice?organization=Target');

    $response->assertOk()->assertJson(['code' => 1]);
    expect($response->json('data.total'))->toBe(1);
});

test('管理员可以查看发票详情', function () {
    $invoice = Invoice::factory()->create(['user_id' => $this->user->id]);

    $response = $this->actingAsAdmin($this->admin)->getJson("/api/admin/invoice/$invoice->id");

    $response->assertOk()->assertJson(['code' => 1]);
    $response->assertJsonPath('data.id', $invoice->id);
});

test('查看不存在的发票返回错误', function () {
    $response = $this->actingAsAdmin($this->admin)->getJson('/api/admin/invoice/99999');

    $response->assertOk()->assertJson(['code' => 0]);
});

test('管理员可以添加发票', function () {
    $response = $this->actingAsAdmin($this->admin)->postJson('/api/admin/invoice', [
        'user_id' => $this->user->id,
        'amount' => '1000.00',
        'organization' => 'Test Corp',
        'taxation' => '1234567890',
        'email' => 'invoice@test.com',
        'status' => 0,
    ]);

    $response->assertOk()->assertJson(['code' => 1]);
    expect(Invoice::where('organization', 'Test Corp')->exists())->toBeTrue();
});

test('管理员可以更新发票', function () {
    $invoice = Invoice::factory()->create(['user_id' => $this->user->id]);

    $response = $this->actingAsAdmin($this->admin)->putJson("/api/admin/invoice/$invoice->id", [
        'status' => 1,
        'remark' => '已开具',
    ]);

    $response->assertOk()->assertJson(['code' => 1]);

    $invoice->refresh();
    expect($invoice->status)->toBe(1);
});

test('管理员可以删除发票', function () {
    $invoice = Invoice::factory()->create(['user_id' => $this->user->id]);

    $response = $this->actingAsAdmin($this->admin)->deleteJson("/api/admin/invoice/$invoice->id");

    $response->assertOk()->assertJson(['code' => 1]);
    expect(Invoice::find($invoice->id))->toBeNull();
});

test('管理员可以批量删除发票', function () {
    $invoices = Invoice::factory()->count(3)->create(['user_id' => $this->user->id]);
    $ids = $invoices->pluck('id')->toArray();

    $response = $this->actingAsAdmin($this->admin)->deleteJson('/api/admin/invoice/batch', [
        'ids' => $ids,
    ]);

    $response->assertOk()->assertJson(['code' => 1]);
    expect(Invoice::whereIn('id', $ids)->count())->toBe(0);
});

test('管理员可以批量获取发票', function () {
    $invoices = Invoice::factory()->count(3)->create(['user_id' => $this->user->id]);
    $ids = $invoices->pluck('id')->toArray();

    $response = $this->actingAsAdmin($this->admin)->getJson('/api/admin/invoice/batch?ids[]=' . implode('&ids[]=', $ids));

    $response->assertOk()->assertJson(['code' => 1]);
});

test('未认证用户无法访问发票管理', function () {
    $response = $this->getJson('/api/admin/invoice');

    $response->assertUnauthorized();
});
