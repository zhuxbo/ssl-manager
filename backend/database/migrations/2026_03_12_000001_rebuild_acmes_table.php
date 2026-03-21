<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. 幂等删除旧 ACME 相关表（禁用外键检查避免依赖顺序问题）
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['acme_authorizations', 'acme_certs', 'acme_orders', 'acme_accounts'] as $table) {
            Schema::dropIfExists($table);
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // 2. 创建统一的 acmes 表
        if (! Schema::hasTable('acmes')) {
            Schema::create('acmes', function (Blueprint $table) {
                $table->bigInteger('id')->unsigned()->primary()->comment('Snowflake ID');
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedInteger('product_id');
                $table->string('brand', 50);
                $table->integer('period')->comment('有效期（月）');
                $table->unsignedInteger('purchased_standard_count')->default(0)->comment('标准域名额度');
                $table->unsignedInteger('purchased_wildcard_count')->default(0)->comment('通配符域名额度');
                $table->string('refer_id', 200)->unique()->nullable()->comment('幂等键');
                $table->string('api_id', 200)->nullable()->comment('上游订单 ID（Gateway 订单 ID）');
                $table->string('vendor_id', 200)->nullable()->comment('CA 订单 ID');
                $table->string('eab_kid', 200)->nullable()->comment('EAB Key ID');
                $table->text('eab_hmac')->nullable()->comment('EAB HMAC（加密存储）');
                $table->timestamp('period_from')->nullable();
                $table->timestamp('period_till')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->enum('status', ['unpaid', 'pending', 'active', 'cancelling', 'cancelled', 'revoked', 'expired']);
                $table->string('remark', 255)->nullable();
                $table->decimal('amount', 10, 2)->comment('订单金额');
                $table->string('admin_remark', 255)->nullable()->comment('管理员备注');
                $table->timestamps();
            });
        }
    }
};
