<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 添加 token_hash 列
        if (! Schema::hasColumn('deploy_tokens', 'token_hash')) {
            Schema::table('deploy_tokens', function (Blueprint $table) {
                $table->string('token_hash', 64)->after('token')->comment('令牌哈希');
            });
        }

        // 旧 token 字段存的是 SHA256 哈希，直接迁移到 token_hash
        DB::table('deploy_tokens')
            ->whereNull('token_hash')
            ->orWhere('token_hash', '')
            ->update(['token_hash' => DB::raw('token')]);

        // 移除 token 的 UNIQUE 索引（已迁移到 token_hash）
        $hasTokenUnique = collect(Schema::getIndexes('deploy_tokens'))->contains('name', 'deploy_tokens_token_unique');
        $hasTokenHashUnique = collect(Schema::getIndexes('deploy_tokens'))->contains('name', 'deploy_tokens_token_hash_unique');
        $tokenCol = collect(Schema::getColumns('deploy_tokens'))->firstWhere('name', 'token');
        $needsTokenResize = $tokenCol && ! str_contains($tokenCol['type'], '1024');

        if ($hasTokenUnique || $needsTokenResize || ! $hasTokenHashUnique) {
            Schema::table('deploy_tokens', function (Blueprint $table) use ($hasTokenUnique, $needsTokenResize, $hasTokenHashUnique) {
                if ($hasTokenUnique) {
                    $table->dropUnique(['token']);
                }

                // token 字段长度改为 1024（加密输出约 200-300 字符）
                if ($needsTokenResize) {
                    $table->string('token', 1024)->comment('令牌')->change();
                }

                // token_hash 添加 UNIQUE 索引
                if (! $hasTokenHashUnique) {
                    $table->unique('token_hash');
                }
            });
        }
    }

    public function down(): void
    {
        // 系统不需要支持回滚
    }
};
