<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary()->comment('ID');
            $table->unsignedBigInteger('user_id')->index()->comment('用户ID');
            $table->unsignedInteger('product_id')->index()->comment('产品ID');
            $table->unsignedBigInteger('latest_cert_id')->nullable()->unique()->comment('最新证书ID');
            $table->string('brand', 50)->default('')->index()->comment('品牌');
            $table->integer('period')->default(0)->comment('购买时长');
            $table->unsignedTinyInteger('plus')->default(0)->comment('赠送时间');
            $table->decimal('amount', 10)->default(0)->comment('金额');
            $table->timestamp('period_from')->nullable()->comment('有效期从');
            $table->timestamp('period_till')->nullable()->comment('有效期到');
            $table->integer('purchased_standard_count')->default(0)->comment('已购标准域名数');
            $table->integer('purchased_wildcard_count')->default(0)->comment('已购通配符数');
            $table->text('organization')->nullable()->comment('组织');
            $table->text('contact')->nullable()->comment('联系人');
            $table->timestamp('cancelled_at')->nullable()->comment('取消时间');
            $table->string('admin_remark', 255)->default('')->comment('管理员备注');
            $table->string('remark', 255)->default('')->comment('会员备注');
            $table->string('eab_kid', 200)->nullable()->index()->comment('EAB Key ID');
            $table->text('eab_hmac')->nullable()->comment('EAB HMAC Key (加密存储)');
            $table->unsignedBigInteger('acme_account_id')->nullable()->comment('连接的 ACME 服务的账户 ID');
            $table->timestamp('eab_used_at')->nullable()->comment('EAB 使用时间（防重放）');
            $table->boolean('auto_renew')->nullable()->comment('自动续费');
            $table->boolean('auto_reissue')->nullable()->comment('自动重签');
            $table->timestamps();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        // 系统采用整体升级方式，不支持回滚操作
    }
};
