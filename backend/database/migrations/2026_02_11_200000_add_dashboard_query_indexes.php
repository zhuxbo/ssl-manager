<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasIndex('users', 'users_created_at_index')) {
                $table->index('created_at');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasIndex('orders', 'orders_created_at_index')) {
                $table->index('created_at');
            }
        });

        Schema::table('certs', function (Blueprint $table) {
            if (! Schema::hasIndex('certs', 'certs_expires_at_index')) {
                $table->index('expires_at');
            }
            if (! Schema::hasIndex('certs', 'certs_issued_at_index')) {
                $table->index('issued_at');
            }
        });

        Schema::table('api_logs', function (Blueprint $table) {
            if (! Schema::hasIndex('api_logs', 'api_logs_created_at_index')) {
                $table->index('created_at');
            }
        });
    }

    public function down(): void
    {
        // 系统采用整体升级方式，不支持回滚操作
    }
};
