<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funds', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary()->comment('ID');
            $table->unsignedBigInteger('user_id')->index()->comment('用户ID');
            $table->decimal('amount', 10)->default(0.00)->comment('金额');
            $table->enum('type', ['addfunds', 'refunds', 'deduct', 'reverse'])->index()
                ->comment('类型:addfunds=充值,refunds=退款,deduct=扣款,reverse=退回');
            $table->enum('pay_method', [
                'wechat',
                'alipay',
                'credit',
                'taobao',
                'pinduoduo',
                'jingdong',
                'douyin',
                'gift',
                'other',
            ])->default('other')->comment('支付方法');
            $table->string('pay_sn', 256)->nullable()->comment('支付编号');
            $table->string('ip', 200)->nullable()->comment('IP');
            $table->string('remark', 500)->nullable()->comment('备注');
            $table->tinyInteger('status')->default(0)->index()->comment('状态: 0=处理中, 1=成功, 2=已退');
            $table->timestamps();

            $table->unique(['type', 'pay_method', 'pay_sn']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funds');
    }
};
