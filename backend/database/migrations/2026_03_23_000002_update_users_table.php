<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 删除废弃列
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'free_cert_quota')) {
                $table->dropColumn('free_cert_quota');
            }
            if (Schema::hasColumn('users', 'invoice_limit')) {
                $table->dropColumn('invoice_limit');
            }
        });

        // 添加 notification_settings 列
        if (! Schema::hasColumn('users', 'notification_settings')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('notification_settings', 255)->nullable()->comment('通知设置')->after('mobile_verified_at');
            });
        }

        // 添加或修正 auto_settings 列
        if (! Schema::hasColumn('users', 'auto_settings')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('auto_settings', 255)->nullable()->comment('自动续费和重签设置')->after('notification_settings');
            });
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->string('auto_settings', 255)->nullable()->comment('自动续费和重签设置')->change();
            });
        }

        // 添加 created_at 索引
        if (! collect(Schema::getIndexes('users'))->contains('name', 'users_created_at_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('created_at');
            });
        }

        // 统一 tinyInteger → unsignedTinyInteger
        $col = collect(Schema::getColumns('users'))->firstWhere('name', 'status');
        if ($col && str_contains($col['type'], 'tinyint') && ! str_contains($col['type'], 'unsigned')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedTinyInteger('status')->default(1)->comment('状态: 0=禁用, 1=启用')->change();
            });
        }
    }

    public function down(): void
    {
        // 系统不需要支持回滚
    }
};
