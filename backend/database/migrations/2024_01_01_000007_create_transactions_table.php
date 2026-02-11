<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index()->comment('用户ID');
            $table->enum('type', ['order', 'cancel', 'addfunds', 'refunds', 'deduct', 'reverse'])->index()->comment('类型:order=订单,cancel=取消,addfunds=充值,refunds=退款,deduct=扣款,reverse=退回');
            $table->unsignedBigInteger('transaction_id')->index()->comment('关联ID（order_id 或 fund_id）');
            $table->decimal('amount', 10)->default(0)->comment('交易金额');
            $table->smallInteger('standard_count')->nullable()->comment('标准域名数量');
            $table->smallInteger('wildcard_count')->nullable()->comment('通配符数量');
            $table->decimal('balance_before', 10)->default(0)->comment('交易前余额');
            $table->decimal('balance_after', 10)->default(0)->comment('交易后余额');
            $table->string('remark', 500)->nullable()->comment('备注');
            $table->timestamp('created_at')->nullable()->index()->comment('创建时间');
        });
    }

    public function down(): void
    {
        // 系统采用整体升级方式，不支持回滚操作
    }
};
