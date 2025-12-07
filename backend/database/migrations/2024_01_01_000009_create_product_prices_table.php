<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_prices', function (Blueprint $table) {
            $table->unsignedInteger('id')->autoIncrement()->comment('ID');
            $table->unsignedInteger('product_id')->comment('产品 ID');
            $table->string('level_code', 50)->default('standard')->index()->comment('级别代码');
            $table->integer('period')->default(12)->index()->comment('购买时长');
            $table->decimal('price', 10)->default(0)->comment('价格');
            $table->decimal('alternative_standard_price', 10)->default(0)->comment('附加标准域名价格');
            $table->decimal('alternative_wildcard_price', 10)->default(0)->comment('附加通配符价格');
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();

            $table->unique(['product_id', 'level_code', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_prices');
    }
};
