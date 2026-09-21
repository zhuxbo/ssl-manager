<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'admin_remark')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('admin_remark', 500)->nullable()->after('status')->comment('管理员备注');
            });
        }
    }
};
