<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('certs', 'vendor_cert_id')) {
            Schema::table('certs', function (Blueprint $table) {
                $table->dropColumn('vendor_cert_id');
            });
        }
    }
};
