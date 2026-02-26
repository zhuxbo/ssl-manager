<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'notification_settings')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('notification_settings', 255)->nullable()->after('mobile_verified_at')->comment('自动续签设置');
            });
        }

        if (! Schema::hasColumn('users', 'auto_settings')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('auto_settings', 255)->nullable()->after('notification_settings')->comment('自动续签设置');
            });
        } else {
            // 存量升级：json → varchar（兼容 MySQL 5.7）
            Schema::table('users', function (Blueprint $table) {
                $table->string('auto_settings', 255)->nullable()->comment('自动续费和重签设置')->change();
            });
        }
    }

    public function down(): void
    {
        // 系统采用整体升级方式，不支持回滚操作
    }
};
