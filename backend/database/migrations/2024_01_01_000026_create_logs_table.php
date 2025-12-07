<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @noinspection DuplicatedCode */
    public function up(): void
    {
        Schema::connection('log')->dropIfExists('ca_logs');
        Schema::connection('log')->create('ca_logs', function (Blueprint $table) {
            $table->id();
            $table->string('url', 2000)->comment('请求URL');
            $table->string('api', 100)->nullable()->comment('API');
            $table->mediumtext('params')->nullable()->comment('请求参数');
            $table->mediumtext('response')->nullable()->comment('响应内容');
            $table->integer('status_code')->default(200)->index()->comment('状态码');
            $table->tinyInteger('status')->default(0)->index()->comment('状态: 0=失败, 1=成功');
            $table->decimal('duration')->default(0)->comment('耗时(秒)');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
        });

        Schema::connection('log')->dropIfExists('callback_logs');
        Schema::connection('log')->create('callback_logs', function (Blueprint $table) {
            $table->id();
            $table->string('method', 10)->index()->comment('请求方法');
            $table->string('url', 2000)->comment('请求地址');
            $table->mediumtext('params')->comment('回调参数');
            $table->mediumtext('response')->nullable()->comment('响应内容');
            $table->string('ip', 100)->nullable()->comment('IP地址');
            $table->tinyInteger('status')->default(0)->index()->comment('状态: 0=失败, 1=成功');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
        });

        Schema::connection('log')->dropIfExists('api_logs');
        Schema::connection('log')->create('api_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index()->comment('用户ID');
            $table->string('version', 10)->default('v2')->index()->comment('API版本');
            $table->string('method', 10)->index()->comment('请求方法');
            $table->string('url', 2000)->comment('请求URL');
            $table->mediumtext('params')->nullable()->comment('请求参数');
            $table->mediumtext('response')->nullable()->comment('响应内容');
            $table->integer('status_code')->default(200)->index()->comment('状态码');
            $table->tinyInteger('status')->default(0)->index()->comment('状态: 0=失败, 1=成功');
            $table->decimal('duration')->default(0)->comment('耗时(秒)');
            $table->string('ip', 100)->nullable()->comment('IP地址');
            $table->string('user_agent', 500)->nullable()->comment('User Agent');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
        });

        Schema::connection('log')->dropIfExists('user_logs');
        Schema::connection('log')->create('user_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index()->comment('用户ID');
            $table->string('module', 100)->index()->comment('模块');
            $table->string('action', 100)->index()->comment('操作');
            $table->string('method', 10)->index()->comment('请求方法');
            $table->string('url', 2000)->comment('请求URL');
            $table->mediumtext('params')->nullable()->comment('请求参数');
            $table->mediumtext('response')->nullable()->comment('响应内容');
            $table->integer('status_code')->default(200)->index()->comment('状态码');
            $table->tinyInteger('status')->default(0)->index()->comment('状态: 0=失败, 1=成功');
            $table->decimal('duration')->default(0)->comment('耗时(秒)');
            $table->string('ip', 100)->nullable()->comment('IP地址');
            $table->string('user_agent', 500)->nullable()->comment('User Agent');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
        });

        Schema::connection('log')->dropIfExists('admin_logs');
        Schema::connection('log')->create('admin_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('admin_id')->nullable()->index()->comment('管理员ID');
            $table->string('module', 100)->index()->comment('模块');
            $table->string('action', 100)->index()->comment('操作');
            $table->string('method', 10)->index()->comment('请求方法');
            $table->string('url', 2000)->comment('请求URL');
            $table->mediumtext('params')->nullable()->comment('请求参数');
            $table->mediumtext('response')->nullable()->comment('响应内容');
            $table->integer('status_code')->default(200)->index()->comment('状态码');
            $table->tinyInteger('status')->default(0)->index()->comment('状态: 0=失败, 1=成功');
            $table->decimal('duration')->default(0)->comment('耗时(秒)');
            $table->string('ip', 100)->nullable()->comment('IP地址');
            $table->string('user_agent', 500)->nullable()->comment('User Agent');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
        });

        Schema::connection('log')->dropIfExists('error_logs');
        Schema::connection('log')->create('error_logs', function (Blueprint $table) {
            $table->id();
            $table->string('method', 10)->index()->comment('请求方法');
            $table->string('url', 2000)->comment('请求URL');
            $table->string('exception', 255)->comment('异常类型');
            $table->string('message', 1000)->comment('错误信息');
            $table->mediumtext('trace')->nullable()->comment('错误堆栈跟踪');
            $table->integer('status_code')->default(500)->index()->comment('状态码');
            $table->string('ip', 100)->nullable()->comment('IP地址');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
        });

        Schema::connection('log')->dropIfExists('easy_logs');
        Schema::connection('log')->create('easy_logs', function (Blueprint $table) {
            $table->id();
            $table->string('method', 10)->index()->comment('请求方法');
            $table->string('url', 2000)->comment('请求URL');
            $table->mediumtext('params')->comment('请求参数');
            $table->mediumtext('response')->nullable()->comment('响应内容');
            $table->string('ip', 100)->nullable()->comment('IP地址');
            $table->tinyInteger('status')->default(0)->index()->comment('状态: 0=失败, 1=成功');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
        });
    }

    public function down(): void
    {
        Schema::connection('log')->dropIfExists('ca_api_logs');
        Schema::connection('log')->dropIfExists('callback_logs');
        Schema::connection('log')->dropIfExists('api_logs');
        Schema::connection('log')->dropIfExists('user_logs');
        Schema::connection('log')->dropIfExists('admin_logs');
        Schema::connection('log')->dropIfExists('error_logs');
        Schema::connection('log')->dropIfExists('easy_logs');
    }
};
