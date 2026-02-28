<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;

test('默认产品类型为 SSL', function () {
    $product = Product::factory()->create(['product_type' => Product::TYPE_SSL]);

    expect($product->isSSL())->toBeTrue();
    expect($product->isCodeSign())->toBeFalse();
    expect($product->isSMIME())->toBeFalse();
    expect($product->isDocSign())->toBeFalse();
});

test('代码签名产品类型判断正确', function () {
    $product = Product::factory()->create(['product_type' => Product::TYPE_CODESIGN]);

    expect($product->isCodeSign())->toBeTrue();
    expect($product->isSSL())->toBeFalse();
});

test('S/MIME 产品类型判断正确', function () {
    $product = Product::factory()->create(['product_type' => Product::TYPE_SMIME]);

    expect($product->isSMIME())->toBeTrue();
    expect($product->isSSL())->toBeFalse();
});

test('文档签名产品类型判断正确', function () {
    $product = Product::factory()->create(['product_type' => Product::TYPE_DOCSIGN]);

    expect($product->isDocSign())->toBeTrue();
    expect($product->isSSL())->toBeFalse();
});

test('产品类型为 null 时视为 SSL', function () {
    $product = new Product;
    $product->product_type = null;

    expect($product->isSSL())->toBeTrue();
});

test('encryption_alg 为数组 cast', function () {
    $product = Product::factory()->create([
        'encryption_alg' => ['RSA-2048', 'RSA-4096', 'EC-256'],
    ]);
    $product->refresh();

    expect($product->encryption_alg)->toBeArray();
    expect($product->encryption_alg)->toContain('RSA-2048');
    expect($product->encryption_alg)->toContain('EC-256');
});

test('periods 为数组 cast', function () {
    $product = Product::factory()->create(['periods' => [12, 24, 36]]);
    $product->refresh();

    expect($product->periods)->toBeArray();
    expect($product->periods)->toContain(12);
    expect($product->periods)->toContain(36);
});

test('validation_methods 为数组 cast', function () {
    $product = Product::factory()->create([
        'validation_methods' => ['txt', 'http', 'email'],
    ]);
    $product->refresh();

    expect($product->validation_methods)->toBeArray();
    expect($product->validation_methods)->toContain('txt');
});

test('common_name_types 和 alternative_name_types 为数组 cast', function () {
    $product = Product::factory()->create([
        'common_name_types' => ['domain', 'ip'],
        'alternative_name_types' => ['standard', 'wildcard'],
    ]);
    $product->refresh();

    expect($product->common_name_types)->toBeArray();
    expect($product->alternative_name_types)->toBeArray();
});

test('产品关联订单', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    Order::factory()->count(3)->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
    ]);

    expect($product->orders)->toHaveCount(3);
});

test('产品关联价格', function () {
    $product = Product::factory()->create();
    ProductPrice::factory()->create(['product_id' => $product->id, 'period' => 12]);
    ProductPrice::factory()->create(['product_id' => $product->id, 'period' => 24]);

    expect($product->prices)->toHaveCount(2);
});

test('brand 保存时自动转小写', function () {
    $product = Product::factory()->create(['brand' => 'DigiCert']);
    $product->refresh();

    expect($product->brand)->toBe('digicert');
});

test('重复 code 自动追加 source 后缀', function () {
    $product1 = Product::factory()->create([
        'code' => 'test_product',
        'source' => 'source1',
    ]);
    $product2 = Product::factory()->create([
        'code' => 'test_product',
        'source' => 'source2',
    ]);

    expect($product1->code)->toBe('test_product');
    expect($product2->code)->toBe('test_product_source2');
});

test('status 字段为整数 cast', function () {
    $product = Product::factory()->create(['status' => '1']);
    $product->refresh();

    expect($product->status)->toBeInt();
    expect($product->status)->toBe(1);
});

test('整数字段正确转换', function () {
    $product = Product::factory()->create([
        'add_san' => '1',
        'replace_san' => '0',
        'reissue' => '1',
        'renew' => '1',
        'refund_period' => '30',
    ]);
    $product->refresh();

    expect($product->add_san)->toBeInt();
    expect($product->replace_san)->toBeInt();
    expect($product->reissue)->toBeInt();
    expect($product->renew)->toBeInt();
    expect($product->refund_period)->toBeInt();
});

test('cost 字段根据 periods 结构化', function () {
    $product = Product::factory()->create([
        'periods' => [12, 24],
        'alternative_name_types' => ['standard', 'wildcard'],
    ]);

    $product->cost = [
        'price' => ['12' => 100, '24' => 180],
        'alternative_standard_price' => ['12' => 50, '24' => 90],
        'alternative_wildcard_price' => ['12' => 80, '24' => 150],
    ];
    $product->save();
    $product->refresh();

    expect($product->cost)->toBeArray();
    expect($product->cost)->toHaveKey('price');
    expect($product->cost['price']['12'])->toBe(100);
});
