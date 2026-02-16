<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'auto_settings')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('auto_settings')->nullable()->after('notification_settings')->comment('自动续签设置');
            });
        }
    }

    public function down(): void
    {
        // 系统采用整体升级方式，不支持回滚操作
    }
};
