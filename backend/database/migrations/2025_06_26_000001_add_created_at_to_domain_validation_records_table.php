<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('domain_validation_records', function (Blueprint $table) {
            // 检查并添加 created_at 字段
            if (! Schema::hasColumn('domain_validation_records', 'created_at')) {
                $table->timestamp('created_at')
                    ->nullable()
                    ->after('next_check_at')
                    ->comment('创建时间');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('domain_validation_records', function (Blueprint $table) {
            // 删除字段
            if (Schema::hasColumn('domain_validation_records', 'created_at')) {
                $table->dropColumn('created_at');
            }
        });
    }
};
