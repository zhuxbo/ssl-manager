<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // api_logs 表添加 created_at 索引
        if (Schema::hasTable('api_logs')) {
            if (! collect(Schema::getIndexes('api_logs'))->contains('name', 'api_logs_created_at_index')) {
                Schema::table('api_logs', function (Blueprint $table) {
                    $table->index('created_at');
                });
            }
        }

        // admin_logs 表添加 created_at 索引
        if (Schema::hasTable('admin_logs')) {
            if (! collect(Schema::getIndexes('admin_logs'))->contains('name', 'admin_logs_created_at_index')) {
                Schema::table('admin_logs', function (Blueprint $table) {
                    $table->index('created_at');
                });
            }
        }
    }

    public function down(): void
    {
        // 系统不需要支持回滚
    }
};
