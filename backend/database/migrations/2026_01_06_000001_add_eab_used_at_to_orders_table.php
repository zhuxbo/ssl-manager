<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'eab_used_at')) {
                $table->timestamp('eab_used_at')->nullable()->after('eab_hmac')->comment('EAB 使用时间（防重放）');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'eab_used_at')) {
                $table->dropColumn('eab_used_at');
            }
        });
    }
};
