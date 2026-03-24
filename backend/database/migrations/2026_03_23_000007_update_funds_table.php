<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('funds')) {
            return;
        }

        if (Schema::getColumnType('funds', 'pay_method') !== 'enum') {
            return;
        }

        Schema::table('funds', function (Blueprint $table) {
            $table->string('pay_method', 32)->default('other')->comment('支付方法')->change();
        });
    }

    public function down(): void
    {
        // 系统不需要支持回滚
    }
};
