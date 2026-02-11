<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deploy_tokens', function (Blueprint $table) {
            if (! Schema::hasColumn('deploy_tokens', 'token_hash')) {
                $table->string('token_hash', 64)->after('token')->comment('令牌哈希');
            }
        });

        // 旧 token 字段存的是 SHA256 哈希，直接迁移到 token_hash
        DB::table('deploy_tokens')
            ->whereNull('token_hash')
            ->orWhere('token_hash', '')
            ->update(['token_hash' => DB::raw('token')]);

        // 移除 token 的 UNIQUE 索引（已迁移到 token_hash）
        $indexes = Schema::getIndexes('deploy_tokens');
        $hasTokenUnique = collect($indexes)->contains(fn ($idx) => $idx['name'] === 'deploy_tokens_token_unique');

        Schema::table('deploy_tokens', function (Blueprint $table) use ($hasTokenUnique) {
            if ($hasTokenUnique) {
                $table->dropUnique(['token']);
            }

            // token 字段长度改为 1024（加密输出约 200-300 字符）
            $table->string('token', 1024)->comment('令牌')->change();

            // token_hash 添加 UNIQUE 索引
            if (! Schema::hasIndex('deploy_tokens', 'deploy_tokens_token_hash_unique')) {
                $table->unique('token_hash');
            }
        });
    }

    public function down(): void
    {
        // 系统采用整体升级方式，不支持回滚操作
    }
};
