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
            $table->string('key_id', 200)->unique()->comment('公钥指纹(kid)');
            $table->json('public_key')->comment('JWK 公钥');
            $table->json('contact')->nullable()->comment('联系方式');
            $table->enum('status', ['valid', 'deactivated', 'revoked'])->default('valid')->comment('状态');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // ACME 订单表 - certbot 发起的证书请求
        Schema::create('acme_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('acme_account_id')->index()->comment('ACME 账户ID');
            $table->unsignedBigInteger('order_id')->nullable()->index()->comment('关联的订单ID');
            $table->json('identifiers')->comment('域名标识符');
            $table->timestamp('expires')->nullable()->comment('过期时间');
            $table->enum('status', ['pending', 'ready', 'processing', 'valid', 'invalid'])->default('pending')->comment('状态');
            $table->string('finalize_token', 100)->unique()->comment('Finalize URL token');
            $table->string('certificate_token', 100)->nullable()->unique()->comment('证书 URL token');
            $table->text('csr')->nullable()->comment('CSR');
            $table->text('certificate')->nullable()->comment('证书');
            $table->text('chain')->nullable()->comment('证书链');
            $table->timestamps();

            $table->foreign('acme_account_id')->references('id')->on('acme_accounts')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('set null');
        });

        // ACME 授权表
        Schema::create('acme_authorizations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('acme_order_id')->index()->comment('ACME 订单ID');
            $table->string('token', 100)->unique()->comment('授权 URL token');
            $table->string('identifier_type', 20)->default('dns')->comment('标识符类型');
            $table->string('identifier_value', 255)->comment('标识符值');
            $table->boolean('wildcard')->default(false)->comment('是否通配符');
            $table->enum('status', ['pending', 'valid', 'invalid', 'deactivated', 'expired', 'revoked'])->default('pending')->comment('状态');
            $table->timestamp('expires')->nullable()->comment('过期时间');

            // 验证信息
            $table->string('challenge_type', 30)->nullable()->comment('验证类型: http-01, dns-01');
            $table->string('challenge_token', 255)->nullable()->comment('验证令牌');
            $table->string('challenge_status', 20)->default('pending')->comment('验证状态');
            $table->timestamp('challenge_validated')->nullable()->comment('验证时间');
            $table->timestamps();

            $table->foreign('acme_order_id')->references('id')->on('acme_orders')->onDelete('cascade');
        });

        // ACME Nonce 表 - 用于防重放攻击
        Schema::create('acme_nonces', function (Blueprint $table) {
            $table->string('nonce', 64)->primary()->comment('Nonce 值');
            $table->timestamp('expires_at')->index()->comment('过期时间');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acme_nonces');
        Schema::dropIfExists('acme_authorizations');
        Schema::dropIfExists('acme_orders');
        Schema::dropIfExists('acme_accounts');
    }
};
