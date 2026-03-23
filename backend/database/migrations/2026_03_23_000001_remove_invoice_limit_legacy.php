<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'invoice_limit')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('invoice_limit');
            });
        }

        Schema::dropIfExists('invoice_limits');
    }

    public function down(): void
    {
        // 不支持回滚
    }
};
