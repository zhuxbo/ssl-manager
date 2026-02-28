<?php

use App\Models\User;
use App\Models\UserRefreshToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

uses(Tests\Traits\ActsAsUser::class);

test('用户登录成功', function () {
    $user = User::factory()->create([
        'password' => 'password123',
        'status' => 1,
    ]);

    $this->postJson('/api/login', [
        'account' => $user->email,
        'password' => 'password123',
    ])
        ->assertOk()
        ->assertJson(['code' => 1])
        ->assertJsonStructure(['data' => ['access_token', 'refresh_token', 'username', 'balance']]);
});

test('用户登录失败-密码错误', function () {
    $user = User::factory()->create([
        'password' => 'password123',
    ]);

    $this->postJson('/api/login', [
        'account' => $user->email,
        'password' => 'wrong_password',
    ])
        ->assertOk()
        ->assertJson(['code' => 0]);
});

test('用户注册成功', function () {
    Cache::put('verify_code_register_newuser@example.com', '123456', 600);

    $this->postJson('/api/register', [
        'username' => 'newuser123',
        'email' => 'newuser@example.com',
        'password' => 'password123',
        'code' => '123456',
    ])
        ->assertOk()
        ->assertJson(['code' => 1])
        ->assertJsonStructure(['data' => ['access_token', 'refresh_token', 'username']]);
});

test('用户注册失败-用户名已存在', function () {
    $user = User::factory()->create();

    $this->postJson('/api/register', [
        'username' => $user->username,
        'email' => 'another@example.com',
        'password' => 'password123',
        'code' => '123456',
    ])
        ->assertOk()
        ->assertJson(['code' => 0]);
});

test('用户注册失败-验证码为空', function () {
    $this->postJson('/api/register', [
        'username' => 'newuser456',
        'email' => 'newuser456@example.com',
        'password' => 'password123',
        'code' => '',
    ])
        ->assertOk()
        ->assertJson(['code' => 0]);
});

test('重置密码成功', function () {
    $user = User::factory()->create([
        'email' => 'reset@example.com',
    ]);

    Cache::put('verify_code_reset_reset@example.com', '123456', 600);

    $this->postJson('/api/reset-password', [
        'email' => 'reset@example.com',
        'password' => 'newpassword123',
        'code' => '123456',
    ])
        ->assertOk()
        ->assertJson(['code' => 1]);
});

test('重置密码失败-邮箱不存在', function () {
    $this->postJson('/api/reset-password', [
        'email' => 'nonexistent@example.com',
        'password' => 'newpassword123',
        'code' => '123456',
    ])
        ->assertOk()
        ->assertJson(['code' => 0]);
});

test('获取当前用户信息', function () {
    $user = User::factory()->create();

    $this->actingAsUser($user)
        ->getJson('/api/me')
        ->assertOk()
        ->assertJson(['code' => 1])
        ->assertJsonStructure(['data' => ['username', 'email', 'balance']]);
});

test('获取用户信息-未认证返回错误', function () {
    $this->getJson('/api/me')
        ->assertUnauthorized();
});

test('修改用户名成功', function () {
    $user = User::factory()->create();

    $this->actingAsUser($user)
        ->patchJson('/api/update-username', [
            'username' => 'updated_username',
        ])
        ->assertOk()
        ->assertJson(['code' => 1]);

    expect($user->fresh()->username)->toBe('updated_username');
});

test('修改密码成功', function () {
    $user = User::factory()->create([
        'password' => 'oldpassword',
    ]);

    $this->actingAsUser($user)
        ->patchJson('/api/update-password', [
            'oldPassword' => 'oldpassword',
            'newPassword' => 'newpassword123',
        ])
        ->assertOk()
        ->assertJson(['code' => 1]);

    expect(Hash::check('newpassword123', $user->fresh()->password))->toBeTrue();
});

test('修改密码失败-旧密码错误', function () {
    $user = User::factory()->create([
        'password' => 'oldpassword',
    ]);

    $this->actingAsUser($user)
        ->patchJson('/api/update-password', [
            'oldPassword' => 'wrongpassword',
            'newPassword' => 'newpassword123',
        ])
        ->assertOk()
        ->assertJson(['code' => 0]);
});

test('修改密码失败-新旧密码相同', function () {
    $user = User::factory()->create([
        'password' => 'samepassword',
    ]);

    $this->actingAsUser($user)
        ->patchJson('/api/update-password', [
            'oldPassword' => 'samepassword',
            'newPassword' => 'samepassword',
        ])
        ->assertOk()
        ->assertJson(['code' => 0]);
});

test('绑定邮箱成功', function () {
    $user = User::factory()->create();

    Cache::put('verify_code_bind_newemail@example.com', '123456', 600);

    $this->actingAsUser($user)
        ->patchJson('/api/bind-email', [
            'email' => 'newemail@example.com',
            'code' => '123456',
        ])
        ->assertOk()
        ->assertJson(['code' => 1]);

    expect($user->fresh()->email)->toBe('newemail@example.com');
});

test('退出登录成功', function () {
    $user = User::factory()->create();

    $this->actingAsUser($user)
        ->deleteJson('/api/logout')
        ->assertOk()
        ->assertJson(['code' => 1]);
});
