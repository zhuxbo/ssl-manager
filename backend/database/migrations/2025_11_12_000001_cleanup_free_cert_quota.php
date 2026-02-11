<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 删除 free_cert_quotas 表
        if (Schema::hasTable('free_cert_quotas')) {
            Schema::dropIfExists('free_cert_quotas');
        }

        // 删除 users 表中的 free_cert_quota 字段
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'free_cert_quota')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('free_cert_quota');
            });
        }
    }

    public function down(): void
    {
        // 系统采用整体升级方式，不支持回滚操作
    }
};
