<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('callbacks', function (Blueprint $table) {
            $table->unsignedInteger('id')->autoIncrement()->comment('ID');
            $table->unsignedBigInteger('user_id')->unique()->comment('用户ID');
            $table->string('url', 500)->comment('回调地址');
            $table->string('token', 500)->nullable()->comment('Token');
            $table->tinyInteger('status')->default(1)->index()->comment('状态: 0=禁用, 1=启用');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('callbacks');
    }
};
