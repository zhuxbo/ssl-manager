<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 检查并添加 users 表的 notification_settings 字段
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'notification_settings')) {
                $table->string('notification_settings')
                    ->nullable()
                    ->after('mobile_verified_at')
                    ->comment('通知设置');
            }
        });

        // 处理 notification_templates 表：将 type 字段重命名为 code
        if (! Schema::hasColumn('notification_templates', 'code') && Schema::hasColumn('notification_templates', 'type')) {
            // 先删除 type 的索引
            try {
                Schema::table('notification_templates', function (Blueprint $table) {
                    $table->dropIndex('notification_templates_type_index');
                });
            } catch (Throwable) {
                // ignore missing index
            }

            // 重命名字段 type 为 code
            Schema::table('notification_templates', function (Blueprint $table) {
                $table->renameColumn('type', 'code');
            });

            // 添加新的索引
            try {
                Schema::table('notification_templates', function (Blueprint $table) {
                    $table->index('code');
                });
            } catch (Throwable) {
                // ignore duplicated index
            }
        } elseif (! Schema::hasColumn('notification_templates', 'code')) {
            // 如果 code 和 type 都不存在，则创建 code 字段
            Schema::table('notification_templates', function (Blueprint $table) {
                $table->string('code', 100)
                    ->after('name')
                    ->comment('模板标识');
            });

            try {
                Schema::table('notification_templates', function (Blueprint $table) {
                    $table->index('code');
                });
            } catch (Throwable) {
                // ignore duplicated index
            }
        }

        // 处理 notification_templates 表：添加 channels 字段
        if (! Schema::hasColumn('notification_templates', 'channels')) {
            Schema::table('notification_templates', function (Blueprint $table) {
                $table->text('channels')->nullable()->after('code')->comment('可用通道');
            });
        }
    }

    public function down(): void
    {
        // 回滚 users 表的 notification_settings 字段
        if (Schema::hasColumn('users', 'notification_settings')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('notification_settings');
            });
        }

        // 回滚 notification_templates 表的 code 字段
        if (Schema::hasColumn('notification_templates', 'code')) {
            // 先删除 code 的索引
            try {
                Schema::table('notification_templates', function (Blueprint $table) {
                    $table->dropIndex('notification_templates_code_index');
                });
            } catch (Throwable) {
                // ignore missing index
            }

            // 将 code 重命名回 type
            Schema::table('notification_templates', function (Blueprint $table) {
                $table->renameColumn('code', 'type');
            });

            // 添加 type 的索引
            try {
                Schema::table('notification_templates', function (Blueprint $table) {
                    $table->index('type');
                });
            } catch (Throwable) {
                // ignore duplicated index
            }
        }

        // 回滚 notification_templates 表的 channels 字段
        if (Schema::hasColumn('notification_templates', 'channels')) {
            Schema::table('notification_templates', function (Blueprint $table) {
                $table->dropColumn('channels');
            });
        }
    }
};
