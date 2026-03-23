<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // invoices 表已迁移至 invoice 插件，invoice_limits 表已废弃
    }

    public function down(): void
    {
        // 系统采用整体升级方式，不支持回滚操作
    }
};
