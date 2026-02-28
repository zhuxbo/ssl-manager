<?php

use App\Models\Organization;
use App\Models\User;

test('组织属于用户', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create(['user_id' => $user->id]);

    expect($org->user)->toBeInstanceOf(User::class);
    expect($org->user->id)->toBe($user->id);
});

test('组织可通过 fillable 设置基本属性', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create([
        'user_id' => $user->id,
        'name' => 'Test Corp',
        'registration_number' => '1234567890',
        'country' => 'CN',
        'state' => 'Beijing',
        'city' => 'Haidian',
        'address' => 'Test Road 123',
        'postcode' => '100000',
        'phone' => '010-12345678',
    ]);

    expect($org->name)->toBe('Test Corp');
    expect($org->registration_number)->toBe('1234567890');
    expect($org->country)->toBe('CN');
    expect($org->state)->toBe('Beijing');
    expect($org->city)->toBe('Haidian');
    expect($org->address)->toBe('Test Road 123');
    expect($org->postcode)->toBe('100000');
    expect($org->phone)->toBe('010-12345678');
});

test('组织创建后可以更新', function () {
    $org = Organization::factory()->create();
    $org->update(['name' => 'Updated Corp']);
    $org->refresh();

    expect($org->name)->toBe('Updated Corp');
});

test('组织可以删除', function () {
    $org = Organization::factory()->create();
    $orgId = $org->id;

    $org->delete();

    expect(Organization::find($orgId))->toBeNull();
});

test('用户可以有多个组织', function () {
    $user = User::factory()->create();
    Organization::factory()->count(3)->create(['user_id' => $user->id]);

    expect($user->organizations)->toHaveCount(3);
});
