<?php

namespace Plugins\Easy\Seeders;

use Illuminate\Database\Seeder;

class PluginSeeder extends Seeder
{
    /**
     * 安装/升级时执行：调用插件内部子 Seeder。
     */
    public function run(): void
    {
        $this->call([
            SettingSeeder::class,
        ]);
    }

    /**
     * 卸载并删除数据时执行：清理插件写入的设置项
     */
    public function clear(): void
    {
        (new SettingSeeder)->clear();
    }
}
