<?php

namespace Tests\Traits;

use App\Models\CnameDelegation;
use App\Models\User;

/**
 * 测试数据创建辅助 Trait
 */
trait CreatesTestData
{
    /**
     * 创建测试用户
     */
    protected function createTestUser(array $overrides = []): User
    {
        $email = $overrides['email'] ?? uniqid().'@test.com';
        unset($overrides['email']);

        return User::firstOrCreate(
            ['email' => $email],
            array_merge([
                'username' => 'test_'.uniqid(),
                'password' => 'password',
                'join_at' => now(),
                'balance' => '100.00',
                'auto_settings' => ['auto_renew' => false, 'auto_reissue' => false],
            ], $overrides)
        );
    }

    /**
     * 创建测试委托记录
     */
    protected function createTestDelegation(User $user, array $overrides = []): CnameDelegation
    {
        $zone = $overrides['zone'] ?? 'example.com';
        $prefix = $overrides['prefix'] ?? '_acme-challenge';

        return CnameDelegation::create(array_merge([
            'user_id' => $user->id,
            'zone' => $zone,
            'prefix' => $prefix,
            'label' => substr(hash('sha256', "$user->id:$prefix.$zone"), 0, 32),
            'valid' => true,
            'fail_count' => 0,
            'last_error' => '',
        ], $overrides));
    }
}
