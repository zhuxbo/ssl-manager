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
        Schema::table('agisos', function (Blueprint $table) {
            // 检查并添加 product_code 字段
            if (! Schema::hasColumn('agisos', 'product_code')) {
                $table->string('product_code', 100)
                    ->nullable()
                    ->after('status')
                    ->comment('产品代码');
            }

            // 检查并添加 period 字段
            if (! Schema::hasColumn('agisos', 'period')) {
                $table->unsignedSmallInteger('period')
                    ->nullable()
                    ->after('product_code')
                    ->comment('周期(月)');
            }
        });

        // 添加索引
        try {
            Schema::table('agisos', function (Blueprint $table) {
                if (! Schema::hasColumn('agisos', 'product_code')) {
                    return;
                }
                $table->index('product_code');
            });
        } catch (Throwable) {
            // ignore duplicated index
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agisos', function (Blueprint $table) {
            // 删除索引
            try {
                $table->dropIndex('agisos_product_code_index');
            } catch (Throwable) {
                // ignore missing index
            }

            // 删除字段
            if (Schema::hasColumn('agisos', 'period')) {
                $table->dropColumn('period');
            }

            if (Schema::hasColumn('agisos', 'product_code')) {
                $table->dropColumn('product_code');
            }
        });
    }
};
