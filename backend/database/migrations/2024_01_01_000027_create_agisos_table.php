<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agisos', function (Blueprint $table) {
            $table->id()->comment('ID');
            $table->unsignedBigInteger('user_id')->nullable()->comment('用户ID');
            $table->unsignedBigInteger('order_id')->nullable()->comment('订单ID');
            $table->string('platform', 20)->nullable()->comment('平台');
            $table->unsignedInteger('type')->nullable()->comment('推送类型');
            $table->string('sign', 200)->nullable()->comment('签名');
            $table->unsignedBigInteger('timestamp')->nullable()->comment('时间戳');
            $table->mediumText('data')->nullable()->comment('推送数据');
            $table->string('tid', 200)->comment('订单ID');
            $table->string('refund_id', 200)->nullable()->comment('退款ID');
            $table->string('status', 50)->nullable()->comment('订单状态');
            $table->string('product_code', 100)->nullable()->comment('产品代码');
            $table->unsignedSmallInteger('period')->nullable()->comment('周期(月)');
            $table->decimal('price', 10)->nullable()->comment('价格');
            $table->unsignedSmallInteger('count')->default(1)->comment('件数');
            $table->decimal('amount', 10)->nullable()->comment('支付金额');
            $table->boolean('recharged')->default(0)->comment('充值状态:0=未充值,1=已充值');
            $table->timestamps();

            // 索引
            $table->index('user_id');
            $table->index('order_id');
            $table->index('tid');
            $table->index('refund_id');
            $table->index('platform');
            $table->index('status');
            $table->index('product_code');
            $table->index('recharged');

            // 外键约束
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('set null');
        });
    }

    public function down(): void
    {
        // 系统采用整体升级方式，不支持回滚操作
    }
};
