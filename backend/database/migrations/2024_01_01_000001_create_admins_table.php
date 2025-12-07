<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->unsignedInteger('id')->autoIncrement()->comment('ID');
            $table->string('username', 20)->unique()->comment('用户名');
            $table->string('email', 50)->nullable()->unique()->comment('邮箱');
            $table->string('mobile', 20)->nullable()->unique()->comment('手机');
            $table->timestamp('last_login_at')->nullable()->comment('上次登录时间');
            $table->string('last_login_ip', 50)->nullable()->comment('上次登录IP');
            $table->string('password', 128)->comment('密码');
            $table->unsignedInteger('token_version')->default(0)->comment('令牌版本');
            $table->timestamp('logout_at')->nullable()->comment('登出时间');
            $table->tinyInteger('status')->default(1)->index()->comment('状态: 0=禁用, 1=启用');
            $table->timestamps();
        });

        Schema::create('admin_refresh_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('admin_id')->index()->comment('管理员ID');
            $table->string('refresh_token', 128)->unique()->comment('刷新令牌');
            $table->timestamp('expires_at')->comment('过期时间');
            $table->timestamp('created_at')->nullable()->comment('创建时间');

            $table->foreign('admin_id')->references('id')->on('admins')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('admin_refresh_tokens', function (Blueprint $table) {
            $table->dropForeign(['admin_id']);
        });
        Schema::dropIfExists('admins');
        Schema::dropIfExists('admin_refresh_tokens');
    }
};
