<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('certs', 'channel')) {
            return;
        }

        // 修改 channel 枚举，添加 deploy 值
        DB::statement("ALTER TABLE `certs` MODIFY COLUMN `channel` ENUM('admin', 'web', 'api', 'acme', 'deploy') NOT NULL COMMENT '渠道'");
    }

    public function down(): void
    {
        if (! Schema::hasColumn('certs', 'channel')) {
            return;
        }

        // 回滚时移除 deploy 值（注意：如果有数据使用 deploy 会失败）
        DB::statement("ALTER TABLE `certs` MODIFY COLUMN `channel` ENUM('admin', 'web', 'api', 'acme') NOT NULL COMMENT '渠道'");
    }
};
