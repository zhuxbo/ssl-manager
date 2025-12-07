<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->unsignedInteger('id')->autoIncrement()->comment('ID');
            $table->string('name', 100)->index()->comment('产品名称');
            $table->string('code', 100)->unique()->comment('产品标识');
            $table->string('api_id', 128)->comment('接口ID');
            $table->string('source', 500)->default('default')->comment('来源');
            $table->enum('product_type', ['ssl', 'codesign', 'smime', 'docsign'])->default('ssl')->index()->comment('产品类型');
            $table->string('brand', 50)->index()->comment('品牌');
            $table->string('ca', 200)->index()->comment('签发机构');
            $table->string('warranty_currency', 20)->default('$')->comment('保障金货币');
            $table->unsignedInteger('warranty')->default(0)->comment('保障金额');
            $table->unsignedInteger('server')->default(0)->comment('服务器授权');
            $table->enum('encryption_standard', ['international', 'chinese'])->default('international')->index()->comment('加密标准');
            $table->string('encryption_alg', 50)->index()->comment('加密算法');
            $table->string('signature_digest_alg', 50)->comment('签名摘要算法');
            $table->enum('validation_type', ['dv', 'ov', 'ev'])->default('dv')->index()->comment('验证类型');
            $table->string('common_name_types', 50)->index()->comment('通用名称类型');
            $table->string('alternative_name_types', 50)->nullable()->index()->comment('备用名称类型');
            $table->string('validation_methods', 200)->comment('验证方法');
            $table->string('periods', 100)->comment('购买时长');
            $table->unsignedInteger('standard_min')->default(0)->comment('标准域名起始个数');
            $table->unsignedInteger('standard_max')->default(0)->comment('标准域名最大个数');
            $table->unsignedInteger('wildcard_min')->default(0)->comment('通配符起始个数');
            $table->unsignedInteger('wildcard_max')->default(0)->comment('通配符最大个数');
            $table->unsignedInteger('total_min')->default(0)->comment('总最少个数');
            $table->unsignedInteger('total_max')->default(0)->comment('总最大个数');
            $table->unsignedTinyInteger('add_san')->default(0)->comment('增加SAN: 0=否, 1=是');
            $table->unsignedTinyInteger('replace_san')->default(0)->comment('替换SAN: 0=否, 1=是');
            $table->unsignedTinyInteger('reissue')->default(0)->comment('重签证书: 0=否, 1=是');
            $table->unsignedTinyInteger('renew')->default(0)->comment('续费证书: 0=否, 1=是');
            $table->unsignedTinyInteger('reuse_csr')->default(0)->comment('重用CSR: 0=否, 1=是');
            $table->unsignedTinyInteger('gift_root_domain')->default(0)->comment('赠送根域名: 0=否, 1=是');
            $table->unsignedTinyInteger('refund_period')->default(30)->comment('退款期限');
            $table->string('remark', 500)->nullable()->comment('备注');
            $table->integer('weight')->default(0)->index()->comment('权重');
            $table->string('cost', 2000)->nullable()->comment('成本');
            $table->unsignedTinyInteger('status')->default(1)->index()->comment('状态: 0=禁用, 1=启用');
            $table->timestamps();

            $table->unique(['source', 'api_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
