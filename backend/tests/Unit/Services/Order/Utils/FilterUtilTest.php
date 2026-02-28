<?php

use App\Services\Order\Utils\FilterUtil;

// ==========================================
// arrayFilterAllowedKeys
// ==========================================

test('arrayFilterAllowedKeys 仅保留允许的键', function () {
    $data = ['name' => 'test', 'email' => 'test@test.com', 'secret' => '123'];
    $result = FilterUtil::arrayFilterAllowedKeys($data, ['name', 'email']);
    expect($result)->toBe(['name' => 'test', 'email' => 'test@test.com']);
});

test('arrayFilterAllowedKeys 空允许列表返回空数组', function () {
    $data = ['name' => 'test'];
    $result = FilterUtil::arrayFilterAllowedKeys($data, []);
    expect($result)->toBe([]);
});

test('arrayFilterAllowedKeys 空数据返回空数组', function () {
    $result = FilterUtil::arrayFilterAllowedKeys([], ['name']);
    expect($result)->toBe([]);
});

test('arrayFilterAllowedKeys 接受逗号分隔字符串', function () {
    $data = ['name' => 'test', 'email' => 'test@test.com', 'secret' => '123'];
    $result = FilterUtil::arrayFilterAllowedKeys($data, 'name,email');
    expect($result)->toBe(['name' => 'test', 'email' => 'test@test.com']);
});

// ==========================================
// filterOrganization
// ==========================================

test('filterOrganization 数组类型过滤字段', function () {
    $org = [
        'name' => 'ACME Corp',
        'country' => 'CN',
        'city' => 'Shanghai',
        'unknown' => 'removed',
    ];
    $result = FilterUtil::filterOrganization($org);
    expect($result)->toHaveKey('name')
        ->toHaveKey('country')
        ->toHaveKey('city')
        ->not->toHaveKey('unknown');
});

test('filterOrganization 整数 ID 直接返回', function () {
    expect(FilterUtil::filterOrganization(42))->toBe(42);
});

test('filterOrganization 字符串 ID 转为整数', function () {
    expect(FilterUtil::filterOrganization('42'))->toBe(42);
});

// ==========================================
// filterContact
// ==========================================

test('filterContact 数组类型过滤字段', function () {
    $contact = [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@test.com',
        'company' => 'removed',
    ];
    $result = FilterUtil::filterContact($contact);
    expect($result)->toHaveKey('first_name')
        ->toHaveKey('last_name')
        ->toHaveKey('email')
        ->not->toHaveKey('company');
});

test('filterContact 整数 ID 直接返回', function () {
    expect(FilterUtil::filterContact(10))->toBe(10);
});

test('filterContact 字符串 ID 转为整数', function () {
    expect(FilterUtil::filterContact('10'))->toBe(10);
});

// ==========================================
// 不同 action 对应不同允许字段（纯单元测试版本）
// ==========================================

test('new 操作允许的字段列表正确', function () {
    $newFields = [
        'is_batch', 'plus', 'unique_value', 'user_id', 'product_id',
        'period', 'validation_method', 'domains', 'contact', 'organization',
    ];
    $params = array_fill_keys($newFields, 'value');
    $params['extra'] = 'should be removed';
    $result = FilterUtil::arrayFilterAllowedKeys($params, $newFields);
    expect($result)->toHaveCount(count($newFields));
});

test('renew 操作需要 order_id 不需要 user_id/product_id', function () {
    $renewFields = [
        'order_id', 'unique_value', 'period', 'validation_method',
        'domains', 'contact', 'organization',
    ];
    $params = ['order_id' => 100, 'user_id' => 1, 'product_id' => 1];
    $result = FilterUtil::arrayFilterAllowedKeys($params, $renewFields);
    expect($result)->toHaveKey('order_id')
        ->not->toHaveKey('user_id')
        ->not->toHaveKey('product_id');
});

test('SMIME new 操作包含 email 字段', function () {
    $smimeNewFields = ['user_id', 'product_id', 'period', 'email', 'contact', 'organization'];
    $params = ['email' => 'test@test.com', 'domains' => 'example.com', 'product_id' => 1];
    $result = FilterUtil::arrayFilterAllowedKeys($params, $smimeNewFields);
    expect($result)->toHaveKey('email')
        ->not->toHaveKey('domains');
});

test('CodeSign reissue 操作只有 order_id', function () {
    $fields = ['order_id'];
    $params = ['order_id' => 100, 'domains' => 'example.com', 'period' => 365];
    $result = FilterUtil::arrayFilterAllowedKeys($params, $fields);
    expect($result)->toHaveKey('order_id')
        ->not->toHaveKey('domains')
        ->not->toHaveKey('period');
});
