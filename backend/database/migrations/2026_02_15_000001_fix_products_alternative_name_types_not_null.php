<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('products', 'alternative_name_types')) {
            // 先将 null 值更新为空数组
            DB::table('products')->whereNull('alternative_name_types')->update(['alternative_name_types' => '[]']);
            // 再修改列定义为 NOT NULL DEFAULT '[]'
            DB::statement("ALTER TABLE `products` MODIFY COLUMN `alternative_name_types` VARCHAR(50) NOT NULL DEFAULT '[]' COMMENT '备用名称类型'");
        }
    }

    public function down(): void
    {
        // 系统采用整体升级方式，不支持回滚操作
    }
};
