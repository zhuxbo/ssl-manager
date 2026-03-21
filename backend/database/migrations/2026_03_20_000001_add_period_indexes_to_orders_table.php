<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasIndex('orders', 'orders_period_from_index')) {
                $table->index('period_from');
            }
            if (! Schema::hasIndex('orders', 'orders_period_till_index')) {
                $table->index('period_till');
            }
        });
    }

    public function down(): void
    {
        // 系统采用整体升级方式，不支持回滚操作
    }
};
