<?php

use App\Models\Admin;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(Tests\Traits\ActsAsAdmin::class);
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::factory()->create();
});

test('管理员可以获取任务列表', function () {
    Task::factory()->count(3)->create();

    $response = $this->actingAsAdmin($this->admin)->getJson('/api/admin/task');

    $response->assertOk()->assertJson(['code' => 1]);
    $response->assertJsonStructure(['data' => ['items', 'total', 'pageSize', 'currentPage']]);
});

test('管理员可以按操作类型筛选任务', function () {
    Task::factory()->create(['action' => 'new']);
    Task::factory()->reissue()->create();

    $response = $this->actingAsAdmin($this->admin)->getJson('/api/admin/task?action=reissue');

    $response->assertOk()->assertJson(['code' => 1]);
    expect($response->json('data.total'))->toBe(1);
});

test('管理员可以按状态筛选任务', function () {
    Task::factory()->create(['status' => 'executing']);
    Task::factory()->failed()->create();

    $response = $this->actingAsAdmin($this->admin)->getJson('/api/admin/task?status=failed');

    $response->assertOk()->assertJson(['code' => 1]);
    expect($response->json('data.total'))->toBe(1);
});

test('管理员可以按订单ID筛选任务', function () {
    $task = Task::factory()->create();
    Task::factory()->create();

    $response = $this->actingAsAdmin($this->admin)->getJson("/api/admin/task?order_id=$task->order_id");

    $response->assertOk()->assertJson(['code' => 1]);
    expect($response->json('data.total'))->toBe(1);
});

test('管理员可以查看任务详情', function () {
    $task = Task::factory()->create();

    $response = $this->actingAsAdmin($this->admin)->getJson("/api/admin/task/$task->id");

    $response->assertOk()->assertJson(['code' => 1]);
    $response->assertJsonPath('data.id', $task->id);
});

test('查看不存在的任务返回错误', function () {
    $response = $this->actingAsAdmin($this->admin)->getJson('/api/admin/task/99999');

    $response->assertOk()->assertJson(['code' => 0]);
});

test('管理员可以删除任务', function () {
    $task = Task::factory()->create();

    $response = $this->actingAsAdmin($this->admin)->deleteJson("/api/admin/task/$task->id");

    $response->assertOk()->assertJson(['code' => 1]);
    expect(Task::find($task->id))->toBeNull();
});

test('管理员可以批量删除任务', function () {
    $tasks = Task::factory()->count(3)->create();
    $ids = $tasks->pluck('id')->toArray();

    $response = $this->actingAsAdmin($this->admin)->deleteJson('/api/admin/task/batch', [
        'ids' => $ids,
    ]);

    $response->assertOk()->assertJson(['code' => 1]);
    expect(Task::whereIn('id', $ids)->count())->toBe(0);
});

test('管理员可以批量启动已停止的任务', function () {
    Queue::fake();

    $tasks = Task::factory()->count(2)->create(['status' => 'stopped']);
    $ids = $tasks->pluck('id')->toArray();

    $response = $this->actingAsAdmin($this->admin)->postJson('/api/admin/task/batch-start', [
        'ids' => $ids,
    ]);

    $response->assertOk()->assertJson(['code' => 1]);

    foreach ($tasks as $task) {
        $task->refresh();
        expect($task->status)->toBe('executing');
    }
});

test('管理员可以批量停止执行中的任务', function () {
    $tasks = Task::factory()->count(2)->create(['status' => 'executing']);
    $ids = $tasks->pluck('id')->toArray();

    $response = $this->actingAsAdmin($this->admin)->postJson('/api/admin/task/batch-stop', [
        'ids' => $ids,
    ]);

    $response->assertOk()->assertJson(['code' => 1]);

    foreach ($tasks as $task) {
        $task->refresh();
        expect($task->status)->toBe('stopped');
    }
});

test('停止非执行中的任务返回错误', function () {
    $tasks = Task::factory()->count(2)->create(['status' => 'successful']);
    $ids = $tasks->pluck('id')->toArray();

    $response = $this->actingAsAdmin($this->admin)->postJson('/api/admin/task/batch-stop', [
        'ids' => $ids,
    ]);

    $response->assertOk()->assertJson(['code' => 0]);
});

test('管理员可以分页获取任务列表', function () {
    Task::factory()->count(15)->create();

    $response = $this->actingAsAdmin($this->admin)->getJson('/api/admin/task?currentPage=2&pageSize=5');

    $response->assertOk()->assertJson(['code' => 1]);
    $response->assertJsonPath('data.currentPage', 2);
    $response->assertJsonPath('data.pageSize', 5);
});

test('未认证用户无法访问任务管理', function () {
    $response = $this->getJson('/api/admin/task');

    $response->assertUnauthorized();
});
