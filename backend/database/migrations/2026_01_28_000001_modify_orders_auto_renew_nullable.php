<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'auto_renew')) {
            return;
        }

        // 将 auto_renew 改为 nullable，并将现有的 false 值更新为 null（回落到用户设置）
        DB::statement('ALTER TABLE `orders` MODIFY COLUMN `auto_renew` TINYINT(1) NULL DEFAULT NULL COMMENT \'自动续费\'');

        // 将原来默认的 false (0) 更新为 null，表示使用用户默认设置
        DB::table('orders')->where('auto_renew', false)->update(['auto_renew' => null]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('orders', 'auto_renew')) {
            return;
        }

        // 将 null 恢复为 false
        DB::table('orders')->whereNull('auto_renew')->update(['auto_renew' => false]);

        // 恢复为 NOT NULL
        DB::statement('ALTER TABLE `orders` MODIFY COLUMN `auto_renew` TINYINT(1) NOT NULL DEFAULT 0 COMMENT \'自动续费\'');
    }
};
