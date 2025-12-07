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
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
