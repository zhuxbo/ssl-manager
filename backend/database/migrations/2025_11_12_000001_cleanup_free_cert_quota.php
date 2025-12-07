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
        // 回滚：重新创建 free_cert_quotas 表
        if (! Schema::hasTable('free_cert_quotas')) {
            Schema::create('free_cert_quotas', function (Blueprint $table) {
                $table->increments('id')->comment('ID');
                $table->unsignedBigInteger('user_id')->index()->comment('用户ID');
                $table->enum('type', ['increase', 'decrease', 'apply', 'cancel'])
                    ->index()
                    ->comment('类型:increase=增加,decrease=减少,apply=申请,cancel=取消');
                $table->unsignedBigInteger('order_id')->nullable()->index()->comment('订单ID');
                $table->integer('quota')->default(0)->comment('变更配额');
                $table->integer('quota_before')->default(0)->comment('变更前配额');
                $table->integer('quota_after')->default(0)->comment('变更后配额');
                $table->timestamp('created_at')->nullable()->comment('创建时间');
            });
        }

        // 回滚：重新添加 users 表中的 free_cert_quota 字段
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'free_cert_quota')) {
            Schema::table('users', function (Blueprint $table) {
                $table->integer('free_cert_quota')
                    ->default(0)
                    ->after('invoice_limit')
                    ->comment('免费证书配额');
            });
        }
    }
};

