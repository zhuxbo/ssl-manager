<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoices')) {
            return;
        }

        Schema::create('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary()->comment('ID');
            $table->unsignedBigInteger('user_id')->index()->comment('用户ID');
            $table->decimal('amount', 10)->default(0)->comment('金额');
            $table->string('organization', 200)->nullable()->index()->comment('组织');
            $table->string('taxation', 100)->nullable()->index()->comment('税号');
            $table->string('remark', 500)->nullable()->comment('备注');
            $table->string('email', 100)->nullable()->comment('邮箱');
            $table->tinyInteger('status')->default(0)->index()->comment('状态: 0=处理中, 1=已开票, 2=已作废');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
