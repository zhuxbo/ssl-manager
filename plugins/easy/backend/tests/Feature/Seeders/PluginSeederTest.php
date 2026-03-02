<?php

use App\Models\Setting;
use App\Models\SettingGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Plugins\Easy\Seeders\PluginSeeder;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('Easy 插件 Seeder 幂等写入 agisoAppSecret 并支持清理', function () {
    $seeder = app(PluginSeeder::class);

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

