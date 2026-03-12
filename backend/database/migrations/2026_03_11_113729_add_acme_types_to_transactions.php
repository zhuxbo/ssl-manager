<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 扩展 enum 值以支持 ACME 独立表
     */
    public function up(): void
    {
        // transactions.type: 添加 acme_order/acme_cancel
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('type', ['order', 'cancel', 'addfunds', 'refunds', 'deduct', 'reverse', 'acme_order', 'acme_cancel'])->change();
        });

        // products.product_type: 添加 acme
        Schema::table('products', function (Blueprint $table) {
            $table->enum('product_type', ['ssl', 'codesign', 'smime', 'docsign', 'acme'])->default('ssl')->change();
        });

        // certs.channel: 添加 deploy、移除 acme
        DB::table('certs')->where('channel', 'acme')->update(['channel' => 'api']);
        Schema::table('certs', function (Blueprint $table) {
            $table->enum('channel', ['admin', 'web', 'api', 'deploy'])->change();
        });
    }
};
