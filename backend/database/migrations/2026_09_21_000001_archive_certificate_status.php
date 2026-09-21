<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $statuses = ['unpaid', 'pending', 'processing', 'approving', 'active', 'archived',
            'cancelling', 'cancelled', 'revoked', 'renewed', 'reissued', 'expired'];
        Schema::table('certs', function (Blueprint $table) use ($statuses) {
            $table->enum('status', [...$statuses, 'failed'])->default('unpaid')->comment('状态')->change();
        });
        DB::table('certs')->where('status', 'failed')->update(['status' => 'archived']);
        Schema::table('certs', function (Blueprint $table) use ($statuses) {
            $table->enum('status', $statuses)->default('unpaid')->comment('状态')->change();
        });
    }

    public function down(): void
    {
        // 系统采用整体升级方式，不支持回滚操作。
    }
};
