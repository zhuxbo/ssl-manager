<?php

use App\Models\Admin;
use App\Models\User;
use Tests\Traits\ActsAsAdmin;
use Tests\Traits\ActsAsUser;

uses(ActsAsAdmin::class, ActsAsUser::class);

test('管理员可新增读取编辑和清除用户备注', function () {
    $this->actingAsAdmin(Admin::factory()->create());
    $payload = ['username' => 'remarkuser', 'password' => 'password123', 'level_code' => 'standard', 'status' => 1];
    $this->postJson('/api/admin/user', $payload + ['admin_remark' => '内部备注'])
        ->assertOk()->assertJsonPath('code', 1);
    $user = User::where('username', 'remarkuser')->firstOrFail();

    $this->getJson("/api/admin/user/$user->id")->assertJsonPath('data.admin_remark', '内部备注');
    $this->getJson('/api/admin/user?username=remarkuser')->assertJsonPath('data.items.0.admin_remark', '内部备注');
    $this->getJson("/api/admin/user/batch?ids[]=$user->id")->assertJsonPath('data.0.admin_remark', '内部备注');

    $this->putJson("/api/admin/user/$user->id", $payload + ['admin_remark' => '修改备注'])
        ->assertJsonPath('code', 1);
    expect($user->fresh()->admin_remark)->toBe('修改备注');

    $this->putJson("/api/admin/user/$user->id", $payload)->assertJsonPath('code', 1);
    expect($user->fresh()->admin_remark)->toBe('修改备注');

    $this->putJson("/api/admin/user/$user->id", $payload + ['admin_remark' => ''])
        ->assertJsonPath('code', 1);
    expect($user->fresh()->admin_remark)->toBeNull();
});

test('快捷备注只改备注且支持清除和长度校验', function () {
    $user = User::factory()->create(['admin_remark' => '原备注']);
    $before = $user->refresh()->getAttributes();
    $this->actingAsAdmin(Admin::factory()->create());

    $this->patchJson("/api/admin/user/remark/$user->id", ['admin_remark' => str_repeat('备', 500)])
        ->assertJsonPath('code', 1);
    expect($user->fresh()->admin_remark)->toBe(str_repeat('备', 500));
    $this->patchJson("/api/admin/user/remark/$user->id", ['admin_remark' => str_repeat('备', 501)])
        ->assertJsonValidationErrors('admin_remark');
    $this->patchJson("/api/admin/user/remark/$user->id", [])->assertJsonValidationErrors('admin_remark');
    expect($user->fresh()->admin_remark)->toBe(str_repeat('备', 500));
    $this->patchJson("/api/admin/user/remark/$user->id", ['admin_remark' => null])->assertJsonPath('code', 1);
    expect($user->fresh()->admin_remark)->toBeNull();
    foreach (['username', 'email', 'level_code', 'custom_level_code', 'balance', 'credit_limit', 'status'] as $key) {
        expect($user->fresh()->getRawOriginal($key))->toBe($before[$key]);
    }
    $this->patchJson('/api/admin/user/remark/99999', ['admin_remark' => '备注'])->assertJsonPath('code', 0);
});

test('用户不能读取或修改管理员备注', function () {
    $user = User::factory()->create(['admin_remark' => '私有备注']);
    expect($user->toArray())->not->toHaveKey('admin_remark');
    $this->actingAsUser($user);
    $this->getJson('/api/me')->assertJsonPath('code', 1)->assertJsonMissingPath('data.admin_remark');
    $this->getJson("/api/admin/user/$user->id")->assertJsonPath('code', 0);
    $this->patchJson("/api/admin/user/remark/$user->id", ['admin_remark' => '篡改'])->assertJsonPath('code', 0);
    expect($user->fresh()->admin_remark)->toBe('私有备注');
});

test('未登录不能更新备注', function () {
    $user = User::factory()->create(['admin_remark' => '原备注']);
    $this->patchJson("/api/admin/user/remark/$user->id", ['admin_remark' => ''])->assertJsonPath('code', 0);
    expect($user->fresh()->admin_remark)->toBe('原备注');
});
