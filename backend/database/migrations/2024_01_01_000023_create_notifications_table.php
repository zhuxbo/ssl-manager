<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->morphs('notifiable');
            $table->unsignedInteger('template_id')->index()->comment('模板ID');
            $table->mediumText('data')->nullable()->comment('通知数据');
            $table->timestamp('read_at')->nullable()->comment('阅读时间');
            $table->timestamp('sent_at')->nullable()->comment('发送时间');
            $table->enum('status', ['pending', 'sending', 'sent', 'failed'])
                ->default('pending')
                ->index()
                ->comment('状态: pending=待发送,sending=发送中,sent=已发送,failed=发送失败');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // 系统采用整体升级方式，不支持回滚操作
    }
};
