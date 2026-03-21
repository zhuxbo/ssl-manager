<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. acme_orders 表 — ACME 独立订单表，Snowflake ID
        if (! Schema::hasTable('acme_orders')) {
            Schema::create('acme_orders', function (Blueprint $table) {
                $table->bigInteger('id')->unsigned()->primary();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedInteger('product_id');
                $table->unsignedBigInteger('latest_cert_id')->nullable();
                $table->string('brand', 50);
                $table->integer('period')->comment('有效期（月）');
                $table->decimal('amount', 10, 2)->default(0)->comment('订单金额');
                $table->unsignedInteger('purchased_standard_count')->default(0);
                $table->unsignedInteger('purchased_wildcard_count')->default(0);
                $table->string('eab_kid', 200)->nullable();
                $table->text('eab_hmac')->nullable()->comment('Laravel encrypted cast');
                $table->timestamp('eab_used_at')->nullable();
                $table->timestamp('period_from')->nullable();
                $table->timestamp('period_till')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->tinyInteger('auto_renew')->nullable();
                $table->tinyInteger('auto_reissue')->nullable();
                $table->string('admin_remark', 255)->nullable();
                $table->string('remark', 255)->nullable();
                $table->timestamps();
            });
        }

        // 2. acme_certs 表 — ACME 独立证书表，Snowflake ID
        if (! Schema::hasTable('acme_certs')) {
            Schema::create('acme_certs', function (Blueprint $table) {
                $table->bigInteger('id')->unsigned()->primary();
                $table->unsignedBigInteger('order_id')->index();
                $table->unsignedBigInteger('last_cert_id')->nullable();
                $table->enum('action', ['new', 'reissue']);
                $table->string('channel', 20)->default('api')->comment('渠道');
                $table->string('api_id', 200)->nullable()->comment('上游系统订单 ID');
                $table->string('vendor_id', 200)->nullable()->comment('CA 订单 ID');
                $table->string('refer_id', 200)->nullable()->unique();
                $table->string('common_name', 256)->default('');
                $table->mediumText('alternative_names')->nullable();
                $table->string('email', 500)->nullable();
                $table->unsignedInteger('standard_count')->default(0);
                $table->unsignedInteger('wildcard_count')->default(0);
                $table->string('validation_method', 20)->nullable()->comment('txt/file');
                $table->mediumText('validation')->nullable()->comment('JSON');
                $table->mediumText('params')->nullable()->comment('JSON, 存 account_id/product_code/order_url');
                $table->decimal('amount', 10, 2)->default(0)->comment('本次扣费金额');
                $table->char('csr_md5', 32)->nullable();
                $table->mediumText('csr')->nullable();
                $table->mediumText('private_key')->nullable();
                $table->mediumText('cert')->nullable();
                $table->mediumText('intermediate_cert')->nullable();
                $table->string('serial_number', 128)->nullable();
                $table->string('issuer', 200)->nullable();
                $table->string('fingerprint', 128)->nullable();
                $table->string('encryption_alg', 20)->nullable();
                $table->unsignedSmallInteger('encryption_bits')->nullable();
                $table->string('signature_digest_alg', 20)->nullable();
                $table->unsignedTinyInteger('cert_apply_status')->default(0)->comment('0=未提交, 2=已提交');
                $table->unsignedTinyInteger('domain_verify_status')->default(0)->comment('0=未开始, 1=进行中, 2=完成');
                $table->timestamp('issued_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('auto_deploy_at')->nullable();
                $table->enum('status', ['unpaid', 'pending', 'processing', 'approving', 'active', 'failed', 'cancelling', 'cancelled', 'revoked', 'reissued', 'expired'])->default('processing');
                $table->timestamps();

                $table->foreign('order_id')->references('id')->on('acme_orders')->onDelete('cascade');
            });
        }

        // 3. acme_authorizations: 删除旧 FK（指向 certs.id），添加新 FK（指向 acme_certs.id）
        if (Schema::hasTable('acme_authorizations')) {
            $oldFkExists = collect(Schema::getForeignKeys('acme_authorizations'))->contains('name', 'acme_authorizations_cert_id_foreign');

            if ($oldFkExists) {
                Schema::table('acme_authorizations', function (Blueprint $table) {
                    $table->dropForeign('acme_authorizations_cert_id_foreign');
                });
            }

            // 清理 cert_id 不在 acme_certs 中的孤儿数据（旧数据指向 certs 表）
            DB::table('acme_authorizations')
                ->whereNotIn('cert_id', DB::table('acme_certs')->select('id'))
                ->delete();

            // 添加新 FK cert_id → acme_certs.id
            $newFkExists = collect(Schema::getForeignKeys('acme_authorizations'))->contains('name', 'acme_authorizations_cert_id_foreign');

            if (! $newFkExists && Schema::hasColumn('acme_authorizations', 'cert_id')) {
                Schema::table('acme_authorizations', function (Blueprint $table) {
                    $table->foreign('cert_id')->references('id')->on('acme_certs')->onDelete('cascade');
                });
            }
        }
    }

    public function down(): void
    {
        // 系统不需要支持回滚
    }
};
