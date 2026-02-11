<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_validation_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->index()->comment('订单ID');
            $table->timestamp('last_check_at')->nullable()->comment('上次验证时间');
            $table->timestamp('next_check_at')->nullable()->comment('下次验证时间');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
        });
    }

    public function down(): void
    {
        // 系统采用整体升级方式，不支持回滚操作
    }
};
