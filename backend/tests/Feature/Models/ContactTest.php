<?php

use App\Models\Contact;
use App\Models\User;

test('联系人属于用户', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create(['user_id' => $user->id]);

    expect($contact->user)->toBeInstanceOf(User::class);
    expect($contact->user->id)->toBe($user->id);
});

test('联系人可通过 fillable 设置基本属性', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create([
        'user_id' => $user->id,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
        'phone' => '13800138000',
        'title' => 'CTO',
    ]);

    expect($contact->first_name)->toBe('John');
    expect($contact->last_name)->toBe('Doe');
    expect($contact->email)->toBe('john@example.com');
    expect($contact->phone)->toBe('13800138000');
    expect($contact->title)->toBe('CTO');
});

test('联系人创建后可以更新', function () {
    $contact = Contact::factory()->create();
    $contact->update(['first_name' => 'Updated']);
    $contact->refresh();

    expect($contact->first_name)->toBe('Updated');
});

test('联系人可以删除', function () {
    $contact = Contact::factory()->create();
    $contactId = $contact->id;

    $contact->delete();

    expect(Contact::find($contactId))->toBeNull();
});

test('用户可以有多个联系人', function () {
    $user = User::factory()->create();
    Contact::factory()->count(3)->create(['user_id' => $user->id]);

    expect($user->contacts)->toHaveCount(3);
});
