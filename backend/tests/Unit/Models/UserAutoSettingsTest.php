<?php

namespace Tests\Unit\Models;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

#[Group('database')]
class UserAutoSettingsTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = DatabaseSeeder::class;

    /**
     * 测试 auto_settings 为 null 时返回默认值
     */
    public function test_returns_default_when_null(): void
    {
        $user = $this->createTestUser();
        $user->auto_settings = null;
        $user->save();

        $user->refresh();

        $this->assertIsArray($user->auto_settings);
        $this->assertFalse($user->auto_settings['auto_renew']);
        $this->assertFalse($user->auto_settings['auto_reissue']);
    }

    /**
     * 测试正确解析 JSON 字符串
     */
    public function test_normalizes_json_string(): void
    {
        $user = $this->createTestUser();

        // 通过 setRawAttributes 设置原始 JSON 字符串
        $user->setRawAttributes(array_merge(
            $user->getAttributes(),
            ['auto_settings' => '{"auto_renew":true,"auto_reissue":false}']
        ));
        $settings = $user->auto_settings;

        $this->assertTrue($settings['auto_renew']);
        $this->assertFalse($settings['auto_reissue']);
    }

    /**
     * 测试正确处理数组输入
     */
    public function test_normalizes_array_input(): void
    {
        $user = $this->createTestUser();
        $user->auto_settings = ['auto_renew' => true, 'auto_reissue' => true];
        $user->save();

        $user->refresh();

        $this->assertTrue($user->auto_settings['auto_renew']);
        $this->assertTrue($user->auto_settings['auto_reissue']);
    }

    /**
     * 测试各种真值转换为 boolean true
     */
    public function test_casts_truthy_to_boolean(): void
    {
        $user = $this->createTestUser();

        // 测试字符串 "1"
        $user->auto_settings = ['auto_renew' => '1', 'auto_reissue' => '0'];
        $user->save();
        $user->refresh();

        $this->assertTrue($user->auto_settings['auto_renew']);
        $this->assertFalse($user->auto_settings['auto_reissue']);

        // 测试整数 1
        $user->auto_settings = ['auto_renew' => 1, 'auto_reissue' => 0];
        $user->save();
        $user->refresh();

        $this->assertTrue($user->auto_settings['auto_renew']);
        $this->assertFalse($user->auto_settings['auto_reissue']);
    }

    /**
     * 测试只返回 auto_renew 和 auto_reissue，忽略其他字段
     */
    public function test_ignores_extra_fields(): void
    {
        $user = $this->createTestUser();
        $user->auto_settings = [
            'auto_renew' => true,
            'auto_reissue' => false,
            'extra_field' => 'should_be_ignored',
        ];
        $user->save();

        $user->refresh();

        $this->assertArrayHasKey('auto_renew', $user->auto_settings);
        $this->assertArrayHasKey('auto_reissue', $user->auto_settings);
        $this->assertArrayNotHasKey('extra_field', $user->auto_settings);
        $this->assertCount(2, $user->auto_settings);
    }

    /**
     * 测试空数组输入返回默认值
     */
    public function test_returns_default_for_empty_array(): void
    {
        $user = $this->createTestUser();
        $user->auto_settings = [];
        $user->save();

        $user->refresh();

        $this->assertFalse($user->auto_settings['auto_renew']);
        $this->assertFalse($user->auto_settings['auto_reissue']);
    }
}
