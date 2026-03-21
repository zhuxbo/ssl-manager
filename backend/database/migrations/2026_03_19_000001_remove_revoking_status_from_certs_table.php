<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('certs', 'status')) {
            Schema::table('certs', function (Blueprint $table) {
                $table->enum('status', [
                    'unpaid', 'pending', 'processing', 'approving', 'active', 'failed',
                    'cancelling', 'cancelled', 'revoked', 'renewed', 'reissued', 'expired',
                ])->default('unpaid')->comment('状态')->change();
            });
        }
    }
};
