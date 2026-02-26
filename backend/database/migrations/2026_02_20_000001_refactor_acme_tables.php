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
        //    必须先删外键+索引，再重命名列（MySQL 不允许删除被外键引用的索引）
        if (Schema::hasColumn('acme_authorizations', 'acme_order_id')) {
            DB::table('acme_authorizations')->truncate();

            Schema::table('acme_authorizations', function (Blueprint $table) {
                $table->dropForeign(['acme_order_id']);
                $table->dropIndex('acme_authorizations_acme_order_id_index');
            });

            Schema::table('acme_authorizations', function (Blueprint $table) {
                $table->renameColumn('acme_order_id', 'cert_id');
                $table->index('cert_id');
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
        Schema::dropIfExists('acme_nonces');

        // 8. acme_authorizations.expires → expires_at
        if (Schema::hasColumn('acme_authorizations', 'expires')) {
            Schema::table('acme_authorizations', function (Blueprint $table) {
                $table->renameColumn('expires', 'expires_at');
            });
        }

        // 9. acme_accounts.order_id 添加外键约束
        $fkOrderExists = collect(DB::select("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'acme_accounts' AND CONSTRAINT_NAME = 'acme_accounts_order_id_foreign'"))->isNotEmpty();

        if (! $fkOrderExists && Schema::hasColumn('acme_accounts', 'order_id')) {
            Schema::table('acme_accounts', function (Blueprint $table) {
                $table->foreign('order_id')->references('id')->on('orders')->onDelete('set null');
            });
        }

        // 10. orders.acme_account_id 添加索引
        $indexExists = collect(DB::select("SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND INDEX_NAME = 'orders_acme_account_id_index'"))->isNotEmpty();

        if (! $indexExists && Schema::hasColumn('orders', 'acme_account_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->index('acme_account_id');
            });
        }

        // 11. acme_accounts.public_key 从 string 改为 text
        if (Schema::hasColumn('acme_accounts', 'public_key')) {
            Schema::table('acme_accounts', function (Blueprint $table) {
                $table->text('public_key')->comment('JWK 公钥')->change();
            });
        }

        // 12. acme_accounts.contact：json → varchar（兼容 MySQL 5.7）
        if (Schema::hasColumn('acme_accounts', 'contact')) {
            Schema::table('acme_accounts', function (Blueprint $table) {
                $table->string('contact', 500)->nullable()->comment('联系方式')->change();
            });
        }
    }

    public function down(): void
    {
        // 系统采用整体升级方式，不支持回滚操作
    }
};
