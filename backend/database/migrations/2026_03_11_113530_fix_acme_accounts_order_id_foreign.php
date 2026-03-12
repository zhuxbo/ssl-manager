<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // acme_accounts.order_id 外键应指向 acme_orders 而非 orders
        $fkExists = collect(Schema::getForeignKeys('acme_accounts'))->contains('name', 'acme_accounts_order_id_foreign');

        if ($fkExists) {
            Schema::table('acme_accounts', function (Blueprint $table) {
                $table->dropForeign('acme_accounts_order_id_foreign');
            });
        }

        // 添加新 FK（如果不存在）
        $newFkExists = collect(Schema::getForeignKeys('acme_accounts'))->contains('name', 'acme_accounts_order_id_foreign');

        if (! $newFkExists) {
            Schema::table('acme_accounts', function (Blueprint $table) {
                $table->foreign('order_id')->references('id')->on('acme_orders')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        // 系统不需要支持回滚
    }
};
