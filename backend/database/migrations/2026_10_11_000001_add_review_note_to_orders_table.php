<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'review_note')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->longText('review_note')->nullable()->after('contact')->comment('文档审核意见');
            });
        }
    }
};
