<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $column = collect(DB::select("SHOW COLUMNS FROM certs WHERE Field = 'status'"))->first();

        if (! $column || str_contains($column->Type, "'revoking'")) {
            return;
        }

        DB::statement("ALTER TABLE `certs` MODIFY COLUMN `status` ENUM('unpaid','pending','processing','approving','active','failed','cancelling','cancelled','revoking','revoked','renewed','reissued','expired') NOT NULL DEFAULT 'unpaid' COMMENT '状态'");
    }

    public function down(): void
    {
        // 系统采用整体升级方式，不支持回滚操作
    }
};
