<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 添加 eab_used_at 字段（确保依赖列存在后再使用 after）
        if (! Schema::hasColumn('orders', 'eab_used_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $column = $table->timestamp('eab_used_at')->nullable()->comment('EAB 使用时间（防重放）');
                if (Schema::hasColumn('orders', 'eab_hmac')) {
                    $column->after('eab_hmac');
                }
            });
        }

        // 添加 auto_reissue 字段（确保依赖列存在后再使用 after）
        if (! Schema::hasColumn('orders', 'auto_reissue')) {
            Schema::table('orders', function (Blueprint $table) {
                $column = $table->boolean('auto_reissue')->nullable()->comment('自动重签');
                if (Schema::hasColumn('orders', 'auto_renew')) {
                    $column->after('auto_renew');
                }
            });
        }

        // 确保 auto_renew 是 nullable（将 false 改为 null 表示使用用户默认设置）
        if (Schema::hasColumn('orders', 'auto_renew')) {
            DB::statement('ALTER TABLE `orders` MODIFY COLUMN `auto_renew` TINYINT(1) NULL DEFAULT NULL COMMENT \'自动续费\'');
            DB::table('orders')->where('auto_renew', false)->update(['auto_renew' => null]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'eab_used_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('eab_used_at');
            });
        }

        if (Schema::hasColumn('orders', 'auto_reissue')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('auto_reissue');
            });
        }
    }
};
