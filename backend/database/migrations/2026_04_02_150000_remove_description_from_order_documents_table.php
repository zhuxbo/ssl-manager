<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_documents', function (Blueprint $table) {
            if (Schema::hasColumn('order_documents', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
};
