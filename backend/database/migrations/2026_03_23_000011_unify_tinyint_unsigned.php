<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->isSignedTinyint('admins', 'status')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->unsignedTinyInteger('status')->default(1)->comment('状态: 0=禁用, 1=启用')->change();
            });
        }

        if ($this->isSignedTinyint('callbacks', 'status')) {
            Schema::table('callbacks', function (Blueprint $table) {
                $table->unsignedTinyInteger('status')->default(1)->comment('状态: 0=禁用, 1=启用')->change();
            });
        }

        if ($this->isSignedTinyint('notification_templates', 'status')) {
            Schema::table('notification_templates', function (Blueprint $table) {
                $table->unsignedTinyInteger('status')->default(1)->comment('状态: 1=启用, 0=禁用')->change();
            });
        }

        if ($this->isSignedTinyint('funds', 'status')) {
            Schema::table('funds', function (Blueprint $table) {
                $table->unsignedTinyInteger('status')->default(0)->comment('状态: 0=处理中, 1=成功, 2=已退')->change();
            });
        }

        foreach (['ca_logs', 'callback_logs', 'api_logs', 'user_logs', 'admin_logs'] as $logTable) {
            if ($this->isSignedTinyint($logTable, 'status')) {
                Schema::table($logTable, function (Blueprint $table) {
                    $table->unsignedTinyInteger('status')->default(0)->comment('状态: 0=失败, 1=成功')->change();
                });
            }
        }
    }

    /**
     * 检查列是否为 signed tinyint（需要转 unsigned）
     */
    private function isSignedTinyint(string $table, string $column): bool
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return false;
        }

        $col = collect(Schema::getColumns($table))->firstWhere('name', $column);

        return $col && str_contains($col['type'], 'tinyint') && ! str_contains($col['type'], 'unsigned');
    }

    public function down(): void
    {
        // 系统不需要支持回滚
    }
};
