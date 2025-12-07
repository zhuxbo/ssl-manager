<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary()->comment('ID');
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->string('last_name', 50)->index()->comment('姓');
            $table->string('first_name', 50)->index()->comment('名');
            $table->string('identification_number', 100)->nullable()->comment('证件编码');
            $table->string('title', 50)->nullable()->comment('职位');
            $table->string('email', 100)->nullable()->index()->comment('邮箱');
            $table->string('phone', 20)->nullable()->index()->comment('电话');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
