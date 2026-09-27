<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('certs', 'cancel_requested_at')) {
            Schema::table('certs', function (Blueprint $table) {
                $table->timestamp('cancel_requested_at')->nullable()->comment('提交取消时间')->after('status');
            });
        }
    }
};
