<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 添加 auto_deploy_at 字段
        if (! Schema::hasColumn('certs', 'auto_deploy_at')) {
            Schema::table('certs', function (Blueprint $table) {
                $table->timestamp('auto_deploy_at')->nullable()->after('expires_at')->comment('自动部署时间');
            });
        }

        // 修改 channel 枚举，添加 deploy 值
        if (Schema::hasColumn('certs', 'channel')) {
            DB::statement("ALTER TABLE `certs` MODIFY COLUMN `channel` ENUM('admin', 'web', 'api', 'acme', 'deploy') NOT NULL COMMENT '渠道'");
        }
    }

    public function down(): void
    {
        // 系统采用整体升级方式，不支持回滚操作
    }
};
