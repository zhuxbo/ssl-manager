<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Seed the admins table.
     */
    public function run(): void
    {
        if (Admin::count() === 0) {
            Admin::create(['username' => 'admin', 'password' => '123456']);
        }
    }
}
