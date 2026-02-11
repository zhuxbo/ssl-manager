<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

        Schema::create('invoice_limits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index()->comment('用户ID');
            $table->enum('type', ['issue', 'void', 'addfunds', 'refunds'])->index()->comment('类型:issue=开票,void=作废,addfunds=充值,refunds=退款');
            $table->unsignedBigInteger('limit_id')->index()->comment('关联ID（invoice_id 或 fund_id）');
            $table->decimal('amount', 10)->default(0)->comment('金额');
            $table->decimal('limit_before', 10)->default(0)->comment('操作前额度');
            $table->decimal('limit_after', 10)->default(0)->comment('操作后额度');
            $table->timestamp('created_at')->nullable()->comment('创建时间');

            $table->unique(['type', 'limit_id']);
        });
    }

    public function down(): void
    {
        // 系统采用整体升级方式，不支持回滚操作
    }
};
