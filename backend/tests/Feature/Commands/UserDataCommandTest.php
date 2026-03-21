<?php

use App\Models\Acme;
use App\Models\ApiToken;
use App\Models\Contact;
use App\Models\DeployToken;
use App\Models\Fund;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    // 清理导出目录
    $dir = storage_path('app/private/exports/users');
    if (File::isDirectory($dir)) {
        File::cleanDirectory($dir);
    }
});

// ===================== export =====================

test('export 导出用户数据为 SQL 文件', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $user->id]);
    Contact::factory()->create(['user_id' => $user->id]);

    $this->artisan("user:data export {$user->id} --force")
        ->assertSuccessful()
        ->expectsOutputToContain('导出完成');

    $files = glob(storage_path("app/private/exports/users/{$user->id}_*.sql"));
    expect($files)->not->toBeEmpty();

    $content = file_get_contents($files[0]);
    expect($content)
        ->toContain("-- user_id: $user->id")
        ->toContain('INSERT INTO `users`')
        ->toContain('INSERT INTO `orders`')
        ->toContain('INSERT INTO `contacts`');
});

test('export 不包含密码', function () {
    $user = User::factory()->create(['password' => 'secret123']);

    $this->artisan("user:data export {$user->id} --force")->assertSuccessful();

    $files = glob(storage_path("app/private/exports/users/{$user->id}_*.sql"));
    $content = file_get_contents($files[0]);

    // password 列应为空字符串
    expect($content)->not->toContain('secret123');
});

test('export 不包含令牌和日志表', function () {
    $user = User::factory()->create();
    ApiToken::factory()->create(['user_id' => $user->id]);
    DeployToken::factory()->create(['user_id' => $user->id]);

    $this->artisan("user:data export {$user->id} --force")->assertSuccessful();

    $files = glob(storage_path("app/private/exports/users/{$user->id}_*.sql"));
    $content = file_get_contents($files[0]);

    expect($content)
        ->not->toContain('INSERT INTO `api_tokens`')
        ->not->toContain('INSERT INTO `deploy_tokens`')
        ->not->toContain('INSERT INTO `user_logs`')
        ->not->toContain('INSERT INTO `api_logs`')
        ->not->toContain('INSERT INTO `user_refresh_tokens`');
});

test('export 包含 ACME 订单', function () {
    $user = User::factory()->create();
    Acme::factory()->create(['user_id' => $user->id]);

    $this->artisan("user:data export {$user->id} --force")->assertSuccessful();

    $files = glob(storage_path("app/private/exports/users/{$user->id}_*.sql"));
    $content = file_get_contents($files[0]);

    expect($content)->toContain('INSERT INTO `acmes`');
});

test('export 用户不存在时失败', function () {
    $this->artisan('user:data export 999999999 --force')
        ->assertFailed()
        ->expectsOutputToContain('不存在');
});

// ===================== import =====================

test('import dry-run 无冲突时提示安全', function () {
    $user = User::factory()->create();
    Contact::factory()->create(['user_id' => $user->id]);

    // 先导出
    $this->artisan("user:data export {$user->id} --force")->assertSuccessful();
    $files = glob(storage_path("app/private/exports/users/{$user->id}_*.sql"));
    $filePath = $files[0];

    // 删除数据
    Contact::where('user_id', $user->id)->delete();
    $user->delete();

    // 干跑检测
    $this->artisan("user:data import {$user->id} --dry-run --file=$filePath")
        ->assertSuccessful()
        ->expectsOutputToContain('无冲突');
});

test('import dry-run 有冲突时报告', function () {
    $user = User::factory()->create();

    // 导出
    $this->artisan("user:data export {$user->id} --force")->assertSuccessful();
    $files = glob(storage_path("app/private/exports/users/{$user->id}_*.sql"));

    // 用户未删除，直接干跑 → 应有冲突
    $this->artisan("user:data import {$user->id} --dry-run --file={$files[0]}")
        ->assertSuccessful()
        ->expectsOutputToContain('冲突');
});

test('import 导入成功', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create(['user_id' => $user->id]);

    // 导出
    $this->artisan("user:data export {$user->id} --force")->assertSuccessful();
    $files = glob(storage_path("app/private/exports/users/{$user->id}_*.sql"));

    // 删除数据
    Contact::where('user_id', $user->id)->delete();
    $user->delete();

    // 导入
    $this->artisan("user:data import {$user->id} --force --file={$files[0]}")
        ->assertSuccessful()
        ->expectsOutputToContain('导入完成');

    // 验证数据恢复
    expect(User::find($user->id))->not->toBeNull();
    expect(Contact::where('user_id', $user->id)->count())->toBe(1);
});

test('import user_id 不匹配时失败', function () {
    $user = User::factory()->create();

    $this->artisan("user:data export {$user->id} --force")->assertSuccessful();
    $files = glob(storage_path("app/private/exports/users/{$user->id}_*.sql"));

    // 用另一个 user_id 导入
    $this->artisan("user:data import 999 --force --file={$files[0]}")
        ->assertFailed()
        ->expectsOutputToContain('不匹配');
});

test('import 无导出文件时提示', function () {
    $this->artisan('user:data import 123 --force')
        ->assertFailed()
        ->expectsOutputToContain('未找到');
});

// ===================== purge =====================

test('purge 用户未禁用时拒绝', function () {
    $user = User::factory()->create(['status' => 1]);

    $this->artisan("user:data purge {$user->id} --force")
        ->assertFailed()
        ->expectsOutputToContain('未禁用');
});

test('purge 未导出时拒绝', function () {
    $user = User::factory()->create(['status' => 0]);

    $this->artisan("user:data purge {$user->id} --force")
        ->assertFailed()
        ->expectsOutputToContain('导出');
});

test('purge 满足条件后清理用户数据', function () {
    $user = User::factory()->create(['status' => 0]);
    $order = Order::factory()->create(['user_id' => $user->id]);
    Contact::factory()->create(['user_id' => $user->id]);
    Organization::factory()->create(['user_id' => $user->id]);
    Fund::factory()->create(['user_id' => $user->id]);
    Transaction::factory()->create(['user_id' => $user->id, 'type' => 'order']);
    Acme::factory()->create(['user_id' => $user->id]);
    DeployToken::factory()->create(['user_id' => $user->id]);
    ApiToken::factory()->create(['user_id' => $user->id]);

    // 先导出
    $this->artisan("user:data export {$user->id} --force")->assertSuccessful();

    // 清理
    $this->artisan("user:data purge {$user->id} --force")
        ->assertSuccessful()
        ->expectsOutputToContain('成功清理');

    // 验证数据已清除
    expect(User::find($user->id))->toBeNull();
    expect(Order::where('user_id', $user->id)->count())->toBe(0);
    expect(Contact::where('user_id', $user->id)->count())->toBe(0);
    expect(Organization::where('user_id', $user->id)->count())->toBe(0);
    expect(Fund::where('user_id', $user->id)->count())->toBe(0);
    expect(Transaction::where('user_id', $user->id)->count())->toBe(0);
    expect(Acme::where('user_id', $user->id)->count())->toBe(0);
    expect(DeployToken::where('user_id', $user->id)->count())->toBe(0);
    expect(ApiToken::where('user_id', $user->id)->count())->toBe(0);
});

// ===================== 参数验证 =====================

test('无效 action 报错', function () {
    $this->artisan('user:data invalid 1 --force')
        ->assertFailed()
        ->expectsOutputToContain('export');
});

test('签名为 user:data', function () {
    $user = User::factory()->create();

    $this->artisan("user:data export {$user->id} --force")->assertSuccessful();
});
