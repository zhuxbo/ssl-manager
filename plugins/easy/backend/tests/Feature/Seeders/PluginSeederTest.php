<?php

use App\Models\Setting;
use App\Models\SettingGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Plugins\Easy\Seeders\PluginSeeder;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('Easy 插件 Seeder 幂等写入 agisoAppSecret 并支持清理', function () {
    $seeder = app(PluginSeeder::class);

    // 先创建 site 组与已有配置，验证插件 Seeder 是按“项”补齐，而不是按“组”短路。
    $siteGroup = SettingGroup::firstOrCreate(
        ['name' => 'site'],
        ['title' => '站点设置', 'description' => null, 'weight' => 1]
    );
    Setting::firstOrCreate(
        ['group_id' => $siteGroup->id, 'key' => 'sourceLevel'],
        [
            'type' => 'array',
            'options' => null,
            'is_multiple' => 0,
            'value' => ['legacy_source' => 'partner'],
            'description' => '历史来源等级',
            'weight' => 99,
        ]
    );

    $seeder->run();
    $seeder->run();

    $siteGroup = SettingGroup::where('name', 'site')->first();
    expect($siteGroup)->not->toBeNull();

    $query = Setting::where('group_id', $siteGroup->id)
        ->where('key', 'agisoAppSecret');

    expect($query->count())->toBe(1);
    expect((string) $query->first()->value)->toBe('');

    $query->first()->update(['value' => 'custom-secret']);

    $seeder->run();
    expect((string) $query->first()->value)->toBe('custom-secret');

    $seeder->clear();
    expect($query->count())->toBe(0);
});
