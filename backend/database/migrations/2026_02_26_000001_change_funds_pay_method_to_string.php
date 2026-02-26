<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('funds')) {
            return;
        }

        $column = collect(DB::select("SHOW COLUMNS FROM funds WHERE Field = 'pay_method'"))->first();

        if (! $column || ! str_starts_with($column->Type, 'enum')) {
            return;
        }

        DB::statement("ALTER TABLE funds MODIFY COLUMN pay_method VARCHAR(32) NOT NULL DEFAULT 'other' COMMENT '支付方法'");
    }

    public function down(): void
    {
        // 系统采用整体升级方式，不支持回滚操作
    }
};
