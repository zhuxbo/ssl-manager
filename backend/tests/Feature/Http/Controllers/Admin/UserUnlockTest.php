<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Traits\ActsAsAdmin;
use Tests\Traits\ActsAsUser;

uses(ActsAsAdmin::class, ActsAsUser::class);

test('管理员解锁全部登录标识并恢复正常登录', function () {
    $user = User::factory()->create([
        'username' => 'UnlockUser',
        'email' => 'Unlock@Example.com',
        'mobile' => '13800138000',
        'password' => 'password123',
        'status' => 1,
    ]);
    $accounts = [$user->username, $user->email, $user->mobile];
    foreach ($accounts as $account) {
        $key = 'user:'.mb_strtolower($account);
        Cache::store('runtime')->put($key.'_locked', true, 600);
        RateLimiter::hit($key, 600);
        RateLimiter::hit($key.':lockout', 600);
    }
    foreach (['admin:unlockuser', 'user:otheruser'] as $key) {
        Cache::store('runtime')->put($key.'_locked', true, 600);
        RateLimiter::hit($key, 600);
    }
    $this->postJson('/api/login', ['account' => $user->email, 'password' => 'password123'])
        ->assertJson(['code' => 0, 'msg' => '您的账号已被锁定，请联系管理员或稍后再试']);

    $this->actingAsAdmin()->postJson("/api/admin/user/unlock/$user->id")
        ->assertOk()->assertJson(['code' => 1]);
    foreach ($accounts as $account) {
        $key = 'user:'.mb_strtolower($account);
        expect(Cache::store('runtime')->has($key.'_locked'))->toBeFalse()
            ->and(RateLimiter::attempts($key))->toBe(0)
            ->and(RateLimiter::attempts($key.':lockout'))->toBe(0);
    }
    foreach (['admin:unlockuser', 'user:otheruser'] as $key) {
        expect(Cache::store('runtime')->has($key.'_locked'))->toBeTrue()
            ->and(RateLimiter::attempts($key))->toBe(1);
    }
    $this->postJson("/api/admin/user/unlock/$user->id")->assertJson(['code' => 1]);
    $this->withHeaders(['Authorization' => ''])->postJson('/api/login', [
        'account' => $user->email,
        'password' => 'password123',
    ])->assertJson(['code' => 1]);
});

test('解锁不会启用禁用用户且跳过空登录标识', function () {
    $user = User::factory()->create(['status' => 0, 'mobile' => null]);
    Cache::store('runtime')->put('user:_locked', true, 600);
    $password = $user->password;
    $this->actingAsAdmin()->postJson("/api/admin/user/unlock/$user->id")
        ->assertJson(['code' => 1]);
    expect($user->fresh()->status)->toBe(0)
        ->and($user->fresh()->password)->toBe($password)
        ->and(Cache::store('runtime')->has('user:_locked'))->toBeTrue();
});

test('解锁不存在用户返回错误', function () {
    $this->actingAsAdmin()->postJson('/api/admin/user/unlock/999999999')
        ->assertJson(['code' => 0, 'msg' => '用户不存在']);
});

test('未认证和普通用户不能解锁', function (bool $authenticated) {
    $user = User::factory()->create();
    $key = 'user:'.mb_strtolower($user->username).'_locked';
    Cache::store('runtime')->put($key, true, 600);
    if ($authenticated) {
        $this->actingAsUser($user);
    }
    $this->postJson("/api/admin/user/unlock/$user->id")->assertUnauthorized();
    expect(Cache::store('runtime')->has($key))->toBeTrue();
})->with([false, true]);
