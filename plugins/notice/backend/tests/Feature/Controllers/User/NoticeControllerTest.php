<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Plugins\Notice\Models\Notice;
use Tests\Traits\ActsAsUser;

uses(Tests\TestCase::class, ActsAsUser::class, RefreshDatabase::class);

test('active 返回激活的公告', function () {
    Notice::factory()->count(2)->create(['is_active' => true]);
    Notice::factory()->create(['is_active' => false]);

    $user = User::factory()->create();
    $response = $this->actingAsUser($user)
        ->getJson('/api/notice/active');

    $response->assertOk()->assertJson(['code' => 1]);
    expect(count($response->json('data')))->toBe(2);
});

test('active 按 sort desc, id desc 排序', function () {
    $a = Notice::factory()->create(['is_active' => true, 'sort' => 0, 'title' => 'A']);
    $b = Notice::factory()->create(['is_active' => true, 'sort' => 10, 'title' => 'B']);
    $c = Notice::factory()->create(['is_active' => true, 'sort' => 10, 'title' => 'C']);

    $user = User::factory()->create();
    $response = $this->actingAsUser($user)
        ->getJson('/api/notice/active');

    $data = $response->json('data');
    expect($data[0]['title'])->toBe('C');
    expect($data[1]['title'])->toBe('B');
    expect($data[2]['title'])->toBe('A');
});

test('active 只返回 id title content type 字段', function () {
    Notice::factory()->create(['is_active' => true]);

    $user = User::factory()->create();
    $response = $this->actingAsUser($user)
        ->getJson('/api/notice/active');

    $item = $response->json('data.0');
    expect(array_keys($item))->toBe(['id', 'title', 'content', 'type']);
});

test('active 无公告时返回空数组', function () {
    $user = User::factory()->create();
    $response = $this->actingAsUser($user)
        ->getJson('/api/notice/active');

    $response->assertOk();
    expect($response->json('data'))->toBe([]);
});

test('未认证访问返回 401', function () {
    $this->getJson('/api/notice/active')->assertUnauthorized();
});
