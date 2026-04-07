<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_verification_reports')) {
            Schema::drop('order_verification_reports');
        }
    }

    public function down(): void
    {
        // 系统不需要支持回滚
    }
};
