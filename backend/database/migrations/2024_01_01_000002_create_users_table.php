<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary()->comment('ID');
            $table->string('username', 20)->unique()->comment('用户名');
            $table->string('email', 50)->nullable()->unique()->comment('邮箱');
            $table->string('mobile', 20)->nullable()->unique()->comment('手机');
            $table->decimal('balance', 10)->default(0)->comment('余额');
            $table->string('level_code', 50)->default('standard')->index()->comment('级别号');
            $table->string('custom_level_code', 100)->nullable()->index()->comment('定制级别号');
            $table->decimal('credit_limit', 10)->default(0)->comment('信用额度');
            $table->decimal('invoice_limit', 10)->default(0)->comment('发票额度');
            $table->timestamp('last_login_at')->nullable()->comment('上次登录时间');
            $table->string('last_login_ip', 50)->nullable()->comment('上次登录IP');
            $table->string('join_ip', 50)->nullable()->comment('加入IP');
            $table->timestamp('join_at')->nullable()->comment('加入时间');
            $table->string('source', 200)->nullable()->comment('来源');
            $table->string('password', 128)->comment('密码');
            $table->unsignedInteger('token_version')->default(0)->comment('令牌版本');
            $table->timestamp('logout_at')->nullable()->comment('登出时间');
            $table->timestamp('email_verified_at')->nullable()->comment('邮箱验证时间');
            $table->timestamp('mobile_verified_at')->nullable()->comment('手机验证时间');
            $table->string('notification_settings')->nullable()->comment('通知设置');
            $table->tinyInteger('status')->default(1)->index()->comment('状态: 0=禁用, 1=启用');
            $table->timestamps();
        });

        Schema::create('user_refresh_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index()->comment('用户ID');
            $table->string('refresh_token', 128)->unique()->comment('刷新令牌');
            $table->timestamp('expires_at')->comment('过期时间');
            $table->timestamp('created_at')->nullable()->comment('创建时间');

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_refresh_tokens', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
        Schema::dropIfExists('users');
        Schema::dropIfExists('user_refresh_tokens');
    }
};
