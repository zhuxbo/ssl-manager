<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tasks', 'user_id')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->after('order_id')->index();
            });
        }
    }
};
