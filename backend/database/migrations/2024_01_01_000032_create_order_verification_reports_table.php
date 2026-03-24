<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_verification_reports')) {
            Schema::create('order_verification_reports', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id')->unique()->comment('订单ID');
                $table->unsignedBigInteger('user_id')->index()->comment('用户ID');
                $table->json('report_data')->comment('验证报告表单数据');
                $table->unsignedTinyInteger('submitted')->default(0)->comment('是否已提交到上游');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // 系统不需要支持回滚
    }
};
