<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 存量升级：去掉 acme_orders 表，acme_authorizations 改为 FK → certs
 * 新部署无需此迁移（原始迁移已创建正确结构）
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. products 表添加 support_acme
        if (! Schema::hasColumn('products', 'support_acme')) {
            Schema::table('products', function (Blueprint $table) {
                $table->tinyInteger('support_acme')->unsigned()->default(0)->after('gift_root_domain')->comment('支持ACME: 0=否, 1=是');
            });
        }

        // 2. acme_authorizations：acme_order_id → cert_id
        if (Schema::hasColumn('acme_authorizations', 'acme_order_id')) {
            DB::table('acme_authorizations')->truncate();

            Schema::table('acme_authorizations', function (Blueprint $table) {
                $table->dropForeign(['acme_order_id']);
                $table->renameColumn('acme_order_id', 'cert_id');
            });
        }

        // 3. acme_authorizations：添加 acme_challenge_id + key_authorization
        if (! Schema::hasColumn('acme_authorizations', 'acme_challenge_id')) {
            Schema::table('acme_authorizations', function (Blueprint $table) {
                $table->unsignedBigInteger('acme_challenge_id')->nullable()->after('challenge_token')->comment('连接的 ACME 服务的 challenge ID');
                $table->text('key_authorization')->nullable()->after('acme_challenge_id')->comment('CA 的 key_authorization');
            });
        }

        // 4. acme_authorizations：cert_id FK → certs
        $fkExists = collect(DB::select("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'acme_authorizations' AND CONSTRAINT_NAME = 'acme_authorizations_cert_id_foreign'"))->isNotEmpty();

        if (! $fkExists && Schema::hasColumn('acme_authorizations', 'cert_id')) {
            Schema::table('acme_authorizations', function (Blueprint $table) {
                $table->foreign('cert_id')->references('id')->on('certs')->onDelete('cascade');
            });
        }

        // 5. acme_accounts 添加 order_id + acme_account_id
        if (! Schema::hasColumn('acme_accounts', 'order_id')) {
            Schema::table('acme_accounts', function (Blueprint $table) {
                $table->unsignedBigInteger('order_id')->nullable()->after('user_id')->comment('关联的订单ID');
                $table->index('order_id');
            });
        }

        if (! Schema::hasColumn('acme_accounts', 'acme_account_id')) {
            Schema::table('acme_accounts', function (Blueprint $table) {
                $table->unsignedBigInteger('acme_account_id')->nullable()->after('order_id')->comment('连接的 ACME 服务的账户 ID');
            });
        }

        // 6. orders 添加 acme_account_id
        if (! Schema::hasColumn('orders', 'acme_account_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->unsignedBigInteger('acme_account_id')->nullable()->after('eab_hmac')->comment('连接的 ACME 服务的账户 ID');
            });
        }

        // 7. 删除旧表
        Schema::dropIfExists('acme_orders');
    }

    public function down(): void
    {
        // 系统采用整体升级方式，不支持回滚操作
    }
};
