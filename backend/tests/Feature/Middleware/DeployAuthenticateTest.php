<?php

use App\Models\DeployToken;
use App\Models\User;

// ==========================================
// Deploy Token 认证
// ==========================================

test('DeployAuthenticate 有效 token 通过', function () {
    $user = User::factory()->create();
    $deployToken = DeployToken::factory()->create([
        'user_id' => $user->id,
        'status' => 1,
    ]);

    $this->withHeaders(['Authorization' => "Bearer $deployToken->token"])
        ->getJson('/api/deploy')
        ->assertOk();
});

test('DeployAuthenticate 无 token 返回错误', function () {
    $this->getJson('/api/deploy')
        ->assertOk()
        ->assertJson(['code' => 0]);
});

test('DeployAuthenticate 无效 token 返回错误', function () {
    $this->withHeaders(['Authorization' => 'Bearer invalid-token'])
        ->getJson('/api/deploy')
        ->assertOk()
        ->assertJson(['code' => 0]);
});

test('DeployAuthenticate 禁用 token 返回错误', function () {
    $user = User::factory()->create();
    $deployToken = DeployToken::factory()->create([
        'user_id' => $user->id,
        'status' => 0,
    ]);

    $this->withHeaders(['Authorization' => "Bearer $deployToken->token"])
        ->getJson('/api/deploy')
        ->assertOk()
        ->assertJson(['code' => 0]);
});

test('DeployAuthenticate IP 受限 token 在不允许的 IP 返回错误', function () {
    $user = User::factory()->create();
    $deployToken = DeployToken::factory()->withAllowedIps(['10.0.0.1'])->create([
        'user_id' => $user->id,
        'status' => 1,
    ]);

    $this->withHeaders(['Authorization' => "Bearer $deployToken->token"])
        ->getJson('/api/deploy')
        ->assertOk()
        ->assertJson(['code' => 0]);
});
