<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ACME 账户表 - 绑定到用户，永久有效
        Schema::create('acme_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index()->comment('用户ID');
            $table->unsignedBigInteger('order_id')->nullable()->index()->comment('关联的订单ID');
            $table->unsignedBigInteger('acme_account_id')->nullable()->comment('连接的 ACME 服务的账户 ID');
            $table->string('key_id', 200)->unique()->comment('公钥指纹(kid)');
            $table->text('public_key')->comment('JWK 公钥');
            $table->string('contact', 500)->nullable()->comment('联系方式');
            $table->enum('status', ['valid', 'deactivated', 'revoked'])->default('valid')->comment('状态');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // ACME 授权表
        Schema::create('acme_authorizations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cert_id')->index()->comment('证书ID');
            $table->string('token', 100)->unique()->comment('授权 URL token');
            $table->string('identifier_type', 20)->default('dns')->comment('标识符类型');
            $table->string('identifier_value', 255)->comment('标识符值');
            $table->boolean('wildcard')->default(false)->comment('是否通配符');
            $table->enum('status', ['pending', 'valid', 'invalid', 'deactivated', 'expired', 'revoked'])->default('pending')->comment('状态');
            $table->timestamp('expires_at')->nullable()->comment('过期时间');

            // 验证信息
            $table->string('challenge_type', 30)->nullable()->comment('验证类型: http-01, dns-01');
            $table->string('challenge_token', 255)->nullable()->comment('验证令牌');
            $table->unsignedBigInteger('acme_challenge_id')->nullable()->comment('连接的 ACME 服务的 challenge ID');
            $table->text('key_authorization')->nullable()->comment('CA 的 key_authorization');
            $table->string('challenge_status', 20)->default('pending')->comment('验证状态');
            $table->timestamp('challenge_validated')->nullable()->comment('验证时间');
            $table->timestamps();

            $table->foreign('cert_id')->references('id')->on('certs')->onDelete('cascade');
        });

    }

    public function down(): void
    {
        // 系统采用整体升级方式，不支持回滚操作
    }
};
