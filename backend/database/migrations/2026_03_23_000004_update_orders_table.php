<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 删除 acme_account_id 索引和列
        if (Schema::hasColumn('orders', 'acme_account_id')) {
            if (collect(Schema::getIndexes('orders'))->contains('name', 'orders_acme_account_id_index')) {
                Schema::table('orders', function (Blueprint $table) {
                    $table->dropIndex('orders_acme_account_id_index');
                });
            }

            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('acme_account_id');
            });
        }

        // 删除 eab 相关列
        $columns = [];
        if (Schema::hasColumn('orders', 'eab_kid')) {
            $columns[] = 'eab_kid';
        }
        if (Schema::hasColumn('orders', 'eab_hmac')) {
            $columns[] = 'eab_hmac';
        }
        if (Schema::hasColumn('orders', 'eab_used_at')) {
            $columns[] = 'eab_used_at';
        }
        if (! empty($columns)) {
            Schema::table('orders', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }

        // 添加 auto_reissue 列
        if (! Schema::hasColumn('orders', 'auto_reissue')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->boolean('auto_reissue')->nullable()->comment('自动重签: null=跟随用户设置, 0=否, 1=是')->after('auto_renew');
            });
        }

        // 修改 auto_renew 为 nullable
        $autoRenewCol = collect(Schema::getColumns('orders'))->firstWhere('name', 'auto_renew');
        if ($autoRenewCol && ! $autoRenewCol['nullable']) {
            Schema::table('orders', function (Blueprint $table) {
                $table->boolean('auto_renew')->nullable()->comment('自动续费: null=跟随用户设置, 0=否, 1=是')->change();
            });
        }

        // 数据迁移：auto_renew=0 → null
        DB::table('orders')->where('auto_renew', 0)->update(['auto_renew' => null]);

        // 添加索引
        if (! collect(Schema::getIndexes('orders'))->contains('name', 'orders_period_from_index')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->index('period_from');
            });
        }

        if (! collect(Schema::getIndexes('orders'))->contains('name', 'orders_period_till_index')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->index('period_till');
            });
        }

        if (! collect(Schema::getIndexes('orders'))->contains('name', 'orders_created_at_index')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        // 系统不需要支持回滚
    }
};
