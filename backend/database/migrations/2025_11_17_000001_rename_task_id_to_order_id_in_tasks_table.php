<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 处理 tasks 表：如果 order_id 不存在且 task_id 存在，则重命名字段
        if (! Schema::hasColumn('tasks', 'order_id') && Schema::hasColumn('tasks', 'task_id')) {
            // 先删除 task_id 的索引
            try {
                Schema::table('tasks', function (Blueprint $table) {
                    $table->dropIndex('tasks_task_id_index');
                });
            } catch (Throwable) {
                // ignore missing index
            }

            // 重命名字段 task_id 为 order_id
            Schema::table('tasks', function (Blueprint $table) {
                $table->renameColumn('task_id', 'order_id');
            });

            // 添加新的索引
            try {
                Schema::table('tasks', function (Blueprint $table) {
                    $table->index('order_id');
                });
            } catch (Throwable) {
                // ignore duplicated index
            }
        } elseif (! Schema::hasColumn('tasks', 'order_id')) {
            // 如果 order_id 和 task_id 都不存在，则创建 order_id
            Schema::table('tasks', function (Blueprint $table) {
                $table->unsignedBigInteger('order_id')
                    ->default(0)
                    ->after('id')
                    ->comment('订单ID');
            });

            try {
                Schema::table('tasks', function (Blueprint $table) {
                    $table->index('order_id');
                });
            } catch (Throwable) {
                // ignore duplicated index
            }
        }
    }

    public function down(): void
    {
        // 系统采用整体升级方式，不支持回滚操作
    }
};
