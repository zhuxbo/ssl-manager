<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 重命名 platform → pay_method（兼容旧版本）
        if (Schema::hasColumn('agisos', 'platform')) {
            Schema::table('agisos', function (Blueprint $table) {
                $table->renameColumn('platform', 'pay_method');
            });

            $map = [
                'TbAlds' => 'taobao',
                'PddAlds' => 'pinduoduo',
                'AldsJd' => 'jingdong',
                'AldsDoudian' => 'douyin',
            ];
            foreach ($map as $old => $new) {
                DB::table('agisos')->where('pay_method', $old)->update(['pay_method' => $new]);
            }
        }

        // 删除 timestamp 列（已无用）
        if (Schema::hasColumn('agisos', 'timestamp')) {
            Schema::table('agisos', function (Blueprint $table) {
                $table->dropColumn('timestamp');
            });
        }
    }

    public function down(): void
    {
        // 插件卸载时由卸载程序处理
    }
};
