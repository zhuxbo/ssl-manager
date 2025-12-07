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
        Admin::firstOrCreate(
            ['username' => 'admin'],
            [
                'username' => 'admin',
                'password' => '123456',
            ]
        );
    }
}
