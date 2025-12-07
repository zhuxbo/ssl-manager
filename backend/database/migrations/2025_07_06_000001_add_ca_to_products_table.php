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
            // 检查并添加 ca 字段
            if (! Schema::hasColumn('products', 'ca')) {
                $table->string('ca', 200)
                    ->after('brand')
                    ->index()
                    ->comment('签发机构');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // 删除索引
            try {
                $table->dropIndex('products_ca_index');
            } catch (Throwable) {
                // ignore missing index
            }

            // 删除字段
            if (Schema::hasColumn('products', 'ca')) {
                $table->dropColumn('ca');
            }
        });
    }
};
