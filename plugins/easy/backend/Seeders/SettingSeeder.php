<?php

namespace Plugins\Easy\Seeders;

use App\Models\Setting;
use App\Models\SettingGroup;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * 幂等新增 easy 插件设置项：仅不存在时创建，默认空字符串。
     */
    public function run(): void
    {
        $siteGroup = SettingGroup::firstOrCreate(
            ['name' => 'site'],
            ['title' => '站点设置', 'description' => null, 'weight' => 1]
        );

        Setting::firstOrCreate(
            ['group_id' => $siteGroup->id, 'key' => 'agisoAppSecret'],
            [
                'type' => 'string',
                'options' => null,
                'is_multiple' => 0,
                'value' => '',
                'description' => '阿奇索AppSecret',
                'weight' => 8,
            ]
        );
    }

    /**
     * 卸载并删除数据时，清理插件创建的设置项。
     */
    public function clear(): void
    {
        $siteGroup = SettingGroup::where('name', 'site')->first();
        if (! $siteGroup) {
            return;
        }

        Setting::where('group_id', $siteGroup->id)
            ->where('key', 'agisoAppSecret')
            ->delete();
    }
}

