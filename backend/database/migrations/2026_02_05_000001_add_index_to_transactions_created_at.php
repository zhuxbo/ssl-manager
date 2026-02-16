<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (! Schema::hasIndex('transactions', 'transactions_created_at_index')) {
                $table->index('created_at');
            }
        });
    }

    public function down(): void
    {
        // 系统采用整体升级方式，不支持回滚操作
    }
};
