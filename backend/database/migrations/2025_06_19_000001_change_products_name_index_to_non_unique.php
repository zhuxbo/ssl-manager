<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 检查表和字段是否存在
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'name')) {
            return;
        }

        // 获取当前索引信息
        $indexes = DB::select("SHOW INDEX FROM products WHERE Column_name = 'name'");

        // 如果存在唯一索引，则删除并创建普通索引
        foreach ($indexes as $index) {
            if ($index->Non_unique == 0) { // 0 表示唯一索引
                Schema::table('products', function (Blueprint $table) use ($index) {
                    // 删除唯一索引
                    try {
                        $table->dropUnique($index->Key_name);
                    } catch (Throwable) {
                        // ignore if index doesn't exist
                    }
                });

                // 添加普通索引
                Schema::table('products', function (Blueprint $table) {
                    try {
                        $table->index('name');
                    } catch (Throwable) {
                        // ignore if index already exists
                    }
                });
                break;
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 检查表和字段是否存在
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'name')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            // 删除普通索引
            try {
                $table->dropIndex('products_name_index');
            } catch (Throwable) {
                // ignore if index doesn't exist
            }

            // 添加唯一索引（注意：只有在数据唯一的情况下才能成功）
            try {
                $table->unique('name');
            } catch (Throwable) {
                // ignore if unique constraint cannot be created due to duplicate data
            }
        });
    }
};
