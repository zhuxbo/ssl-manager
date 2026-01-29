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
        Schema::table('products', function (Blueprint $table) {
            // 检查 validation_methods 字段是否存在
            if (Schema::hasColumn('products', 'validation_methods')) {
                // 将 validation_methods 字段长度从 100 增加到 200
                $table->string('validation_methods', 200)
                    ->change()
                    ->comment('验证方法');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // 回滚：将 validation_methods 字段长度改回 100
            if (Schema::hasColumn('products', 'validation_methods')) {
                $table->string('validation_methods', 100)
                    ->change()
                    ->comment('验证方法');
            }
        });
    }
};
