<?php

namespace Database\Seeders;

use App\Models\UserLevel;
use Illuminate\Database\Seeder;

class UserLevelSeeder extends Seeder
{
    /**
     * Seed the user_levels table.
     */
    public function run(): void
    {
        $userLevels = [
            ['name' => '标准会员', 'code' => 'standard', 'custom' => 0, 'weight' => 1],
            ['name' => '金牌会员', 'code' => 'gold', 'custom' => 0, 'weight' => 2],
            ['name' => '铂金会员', 'code' => 'platinum', 'custom' => 0, 'weight' => 3],
            ['name' => '皇冠会员', 'code' => 'crown', 'custom' => 0, 'weight' => 4],
            ['name' => '合作伙伴', 'code' => 'partner', 'custom' => 0, 'weight' => 5],
        ];

        foreach ($userLevels as $level) {
            UserLevel::firstOrCreate(
                ['code' => $level['code']],
                $level
            );
        }
    }
}
