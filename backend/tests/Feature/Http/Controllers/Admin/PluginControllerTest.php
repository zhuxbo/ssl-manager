<?php

use App\Models\Admin;
use App\Services\Plugin\PluginManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\Traits\ActsAsAdmin::class);
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::factory()->create();
});

test('管理员可以获取已安装插件列表', function () {
    $mock = Mockery::mock(PluginManager::class);
    $mock->shouldReceive('getInstalledPlugins')->once()->andReturn([]);
    $this->app->instance(PluginManager::class, $mock);

    $response = $this->actingAsAdmin($this->admin)->getJson('/api/admin/plugin/installed');

    $response->assertOk()->assertJson(['code' => 1]);
    $response->assertJsonStructure(['data' => ['plugins']]);
});

test('管理员可以检查插件更新', function () {
    $mock = Mockery::mock(PluginManager::class);
    $mock->shouldReceive('checkUpdates')->once()->andReturn([]);
    $this->app->instance(PluginManager::class, $mock);

    $response = $this->actingAsAdmin($this->admin)->getJson('/api/admin/plugin/check-updates');

    $response->assertOk()->assertJson(['code' => 1]);
    $response->assertJsonStructure(['data' => ['updates']]);
});

test('管理员可以远程安装插件', function () {
    $mock = Mockery::mock(PluginManager::class);
    $mock->shouldReceive('install')->once()->andReturn(['name' => 'test-plugin', 'version' => '1.0.0']);
    $this->app->instance(PluginManager::class, $mock);

    $response = $this->actingAsAdmin($this->admin)->postJson('/api/admin/plugin/install', [
        'name' => 'test-plugin',
    ]);

    $response->assertOk()->assertJson(['code' => 1]);
});

test('安装插件未指定名称和文件返回错误', function () {
    $response = $this->actingAsAdmin($this->admin)->postJson('/api/admin/plugin/install', []);

    $response->assertOk()->assertJson(['code' => 0]);
});

test('管理员可以更新插件', function () {
    $mock = Mockery::mock(PluginManager::class);
    $mock->shouldReceive('update')->once()->andReturn(['name' => 'test-plugin', 'version' => '1.1.0']);
    $this->app->instance(PluginManager::class, $mock);

    $response = $this->actingAsAdmin($this->admin)->postJson('/api/admin/plugin/update', [
        'name' => 'test-plugin',
    ]);

    $response->assertOk()->assertJson(['code' => 1]);
});

test('更新插件未指定名称返回错误', function () {
    $response = $this->actingAsAdmin($this->admin)->postJson('/api/admin/plugin/update', []);

    $response->assertOk()->assertJson(['code' => 0]);
});

test('管理员可以卸载插件', function () {
    $mock = Mockery::mock(PluginManager::class);
    $mock->shouldReceive('uninstall')->once()->andReturn(['name' => 'test-plugin']);
    $this->app->instance(PluginManager::class, $mock);

    $response = $this->actingAsAdmin($this->admin)->postJson('/api/admin/plugin/uninstall', [
        'name' => 'test-plugin',
    ]);

    $response->assertOk()->assertJson(['code' => 1]);
});

test('卸载插件未指定名称返回错误', function () {
    $response = $this->actingAsAdmin($this->admin)->postJson('/api/admin/plugin/uninstall', []);

    $response->assertOk()->assertJson(['code' => 0]);
});

test('管理员可以卸载插件并删除数据', function () {
    $mock = Mockery::mock(PluginManager::class);
    $mock->shouldReceive('uninstall')->with('test-plugin', true)->once()->andReturn(['name' => 'test-plugin']);
    $this->app->instance(PluginManager::class, $mock);

    $response = $this->actingAsAdmin($this->admin)->postJson('/api/admin/plugin/uninstall', [
        'name' => 'test-plugin',
        'remove_data' => true,
    ]);

    $response->assertOk()->assertJson(['code' => 1]);
});

test('未认证用户无法访问插件管理', function () {
    $response = $this->getJson('/api/admin/plugin/installed');

    $response->assertUnauthorized();
});
