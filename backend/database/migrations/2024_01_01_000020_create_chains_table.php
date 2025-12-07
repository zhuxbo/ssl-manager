<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chains', function (Blueprint $table) {
            $table->unsignedInteger('id')->autoIncrement()->comment('ID');
            $table->string('common_name', 200)->unique()->comment('通用名称');
            $table->text('intermediate_cert')->comment('中级证书');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chains');
    }
};
