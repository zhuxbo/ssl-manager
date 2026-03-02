<?php

use App\Models\Setting;
use App\Models\SettingGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('AGISO 回调-淘宝测试推送 SKU 无法解析时仍写入 agisos 表', function () {
    $secret = 'agiso-secret-for-test';

    $group = SettingGroup::firstOrCreate(
        ['name' => 'site'],
        ['title' => 'Site', 'weight' => 0]
    );
    Setting::updateOrCreate(
        ['group_id' => $group->id, 'key' => 'agisoAppSecret'],
        ['value' => $secret, 'type' => 'string']
    );

    $jsonData = [
        'Platform' => 'TAOBAO',
        'PlatformUserId' => '234234234',
        'Tid' => 2067719225654838,
        'Status' => 'WAIT_BUYER_CONFIRM_GOODS',
        'SellerNick' => '测试的店铺',
        'BuyerNick' => '西门吹雪',
        'BuyerOpenUid' => 'AAEG_gqxAASh85ddgeq8L2AC',
        'Price' => '3.00',
        'Num' => 1,
        'TotalFee' => '3.00',
        'Payment' => '3.00',
        'PayTime' => '2016-07-11 11:20:20',
        'Created' => '2016-07-11 11:20:09',
        'Orders' => [[
            'Num' => 1,
            'NumIid' => 45533870790,
            'Oid' => 2067719225654838,
            'OuterIid' => 'ALDS_1000',
            'OuterSkuId' => 'ALDS_SKU_1000',
            'Payment' => '3.00',
            'Price' => '3.00',
            'Title' => '宝贝标题',
            'TotalFee' => '3.00',
        ]],
    ];

    $json = json_encode($jsonData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $timestamp = (string) time();
    $sign = md5($secret.'json'.$json.'timestamp'.$timestamp.$secret);

    $this->postJson('/callback/agiso', [
        'json' => $json,
        'aopic' => '21',
        'timestamp' => $timestamp,
        'sign' => $sign,
    ])
        ->assertOk()
        ->assertJson(['code' => 1]);

    $this->assertDatabaseHas('agisos', [
        'type' => 21,
        'tid' => (string) $jsonData['Tid'],
        'pay_method' => 'taobao',
        'status' => 'WAIT_BUYER_CONFIRM_GOODS',
        'product_code' => null,
        'period' => null,
        'price' => '3.00',
        'amount' => '3.00',
        'count' => 1,
    ]);
});

