<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = ['eab_kid', 'eab_hmac', 'eab_used_at'];
        $toDrop = array_filter($columns, fn ($col) => Schema::hasColumn('orders', $col));

        if (! empty($toDrop)) {
            Schema::table('orders', function (Blueprint $table) use ($toDrop) {
                $table->dropColumn($toDrop);
            });
        }
    }
};
