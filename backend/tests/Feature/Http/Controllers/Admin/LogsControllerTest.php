<?php

use App\Models\Admin;
use App\Models\AdminLog;
use App\Models\ApiLog;
use App\Models\CallbackLog;
use App\Models\CaLog;
use App\Models\ErrorLog;
use App\Models\UserLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\Traits\ActsAsAdmin::class);
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::factory()->create();
});

test('管理员可以获取管理端日志列表', function () {
    $response = $this->actingAsAdmin($this->admin)->getJson('/api/admin/logs/admin');

    $response->assertOk()->assertJson(['code' => 1]);
    $response->assertJsonStructure(['data' => ['items', 'total', 'pageSize', 'currentPage']]);
});

test('管理员可以获取用户端日志列表', function () {
    $response = $this->actingAsAdmin($this->admin)->getJson('/api/admin/logs/user');

    $response->assertOk()->assertJson(['code' => 1]);
    $response->assertJsonStructure(['data' => ['items', 'total', 'pageSize', 'currentPage']]);
});

test('管理员可以获取API日志列表', function () {
    $response = $this->actingAsAdmin($this->admin)->getJson('/api/admin/logs/api');

    $response->assertOk()->assertJson(['code' => 1]);
    $response->assertJsonStructure(['data' => ['items', 'total', 'pageSize', 'currentPage']]);
});

test('管理员可以获取回调日志列表', function () {
    $response = $this->actingAsAdmin($this->admin)->getJson('/api/admin/logs/callback');

    $response->assertOk()->assertJson(['code' => 1]);
    $response->assertJsonStructure(['data' => ['items', 'total', 'pageSize', 'currentPage']]);
});

test('管理员可以获取CA日志列表', function () {
    $response = $this->actingAsAdmin($this->admin)->getJson('/api/admin/logs/ca');

    $response->assertOk()->assertJson(['code' => 1]);
    $response->assertJsonStructure(['data' => ['items', 'total', 'pageSize', 'currentPage']]);
});

test('管理员可以获取错误日志列表', function () {
    $response = $this->actingAsAdmin($this->admin)->getJson('/api/admin/logs/error');

    $response->assertOk()->assertJson(['code' => 1]);
    $response->assertJsonStructure(['data' => ['items', 'total', 'pageSize', 'currentPage']]);
});

test('管理员可以按URL筛选日志', function () {
    $response = $this->actingAsAdmin($this->admin)->getJson('/api/admin/logs/admin?url=/api/admin');

    $response->assertOk()->assertJson(['code' => 1]);
});

test('管理员可以按状态筛选日志', function () {
    $response = $this->actingAsAdmin($this->admin)->getJson('/api/admin/logs/admin?status=1');

    $response->assertOk()->assertJson(['code' => 1]);
});

test('管理员可以按IP筛选日志', function () {
    $response = $this->actingAsAdmin($this->admin)->getJson('/api/admin/logs/admin?ip=127.0.0.1');

    $response->assertOk()->assertJson(['code' => 1]);
});

test('管理员可以按日期范围筛选日志', function () {
    $response = $this->actingAsAdmin($this->admin)->getJson('/api/admin/logs/admin?created_at[]=' . now()->subDay()->format('Y-m-d\TH:i:s.v\Z') . '&created_at[]=' . now()->format('Y-m-d\TH:i:s.v\Z'));

    $response->assertOk()->assertJson(['code' => 1]);
});

test('管理员可以分页获取日志', function () {
    $response = $this->actingAsAdmin($this->admin)->getJson('/api/admin/logs/admin?currentPage=1&pageSize=5');

    $response->assertOk()->assertJson(['code' => 1]);
    $response->assertJsonPath('data.currentPage', 1);
    $response->assertJsonPath('data.pageSize', 5);
});

test('未认证用户无法访问日志', function () {
    $response = $this->getJson('/api/admin/logs/admin');

    $response->assertUnauthorized();
});
