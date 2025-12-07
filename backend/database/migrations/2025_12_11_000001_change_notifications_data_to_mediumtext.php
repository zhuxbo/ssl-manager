<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('notifications', 'data')) {
            DB::statement('ALTER TABLE notifications MODIFY data MEDIUMTEXT NULL COMMENT \'通知数据\'');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('notifications', 'data')) {
            DB::statement('ALTER TABLE notifications MODIFY data TEXT NULL COMMENT \'通知数据\'');
        }
    }
};
