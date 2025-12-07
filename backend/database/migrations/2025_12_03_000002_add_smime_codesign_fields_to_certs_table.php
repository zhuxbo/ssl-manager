<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certs', function (Blueprint $table) {
            if (! Schema::hasColumn('certs', 'email')) {
                $table->string('email', 500)->nullable()->after('alternative_names')->index()->comment('邮箱地址');
            }
            if (! Schema::hasColumn('certs', 'documents')) {
                $table->string('documents', 2000)->nullable()->after('validation')->comment('验证文档列表');
            }
        });
    }

    public function down(): void
    {
        Schema::table('certs', function (Blueprint $table) {
            if (Schema::hasColumn('certs', 'email')) {
                $table->dropColumn('email');
            }
            if (Schema::hasColumn('certs', 'documents')) {
                $table->dropColumn('documents');
            }
        });
    }
};
