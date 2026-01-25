<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 需要修改的日志表及其字段
        $tables = [
            'ca_logs' => 'url',
            'callback_logs' => 'url',
            'api_logs' => 'url',
            'admin_logs' => 'url',
            'user_logs' => 'url',
            'error_logs' => 'url',
            'easy_logs' => 'url',
        ];

        foreach ($tables as $tableName => $columnName) {
            // 检查表和字段是否存在
            if (Schema::hasTable($tableName) &&
                Schema::hasColumn($tableName, $columnName)) {
                Schema::table($tableName, function (Blueprint $table) use ($columnName) {
                    // 将 url 字段长度从 500 增加到 2000
                    $table->string($columnName, 2000)
                        ->change()
                        ->comment('请求URL');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 需要还原的日志表及其字段
        $tables = [
            'ca_logs' => 'url',
            'callback_logs' => 'url',
            'api_logs' => 'url',
            'admin_logs' => 'url',
            'user_logs' => 'url',
            'error_logs' => 'url',
            'easy_logs' => 'url',
        ];

        foreach ($tables as $tableName => $columnName) {
            // 检查表和字段是否存在
            if (Schema::hasTable($tableName) &&
                Schema::hasColumn($tableName, $columnName)) {
                Schema::table($tableName, function (Blueprint $table) use ($columnName) {
                    // 将 url 字段长度还原为 500
                    $table->string($columnName, 500)
                        ->change()
                        ->comment('请求URL');
                });
            }
        }
    }
};
