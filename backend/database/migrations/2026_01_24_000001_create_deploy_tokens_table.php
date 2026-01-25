<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deploy_tokens', function (Blueprint $table) {
            $table->unsignedInteger('id')->autoIncrement()->comment('ID');
            $table->unsignedBigInteger('user_id')->index()->comment('用户ID');
            $table->string('token', 128)->unique()->comment('令牌');
            $table->string('allowed_ips', 2000)->nullable()->comment('允许的IP');
            $table->integer('rate_limit')->default(60)->comment('速率限制');
            $table->timestamp('last_used_at')->nullable()->comment('最后使用时间');
            $table->string('last_used_ip', 100)->nullable()->comment('最后使用IP');
            $table->unsignedTinyInteger('status')->default(1)->index()->comment('状态: 0=禁用, 1=启用');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deploy_tokens');
    }
};
