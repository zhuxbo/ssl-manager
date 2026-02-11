<?php

namespace Tests\Unit\Models;

use App\Models\DeployToken;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

#[Group('database')]
class DeployTokenTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = DatabaseSeeder::class;

    private function createDeployToken(array $overrides = []): DeployToken
    {
        $user = $this->createTestUser();

        return DeployToken::create(array_merge([
            'user_id' => $user->id,
            'token' => 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4',
            'status' => 1,
        ], $overrides));
    }

    /**
     * 测试 token 存储为加密值，同时生成 token_hash
     */
    public function test_token_is_encrypted_on_save(): void
    {
        $rawToken = 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4';
        $token = $this->createDeployToken(['token' => $rawToken]);

        // 数据库中 token 字段不是原始值
        $dbRaw = $token->getRawOriginal('token');
        $this->assertNotEquals($rawToken, $dbRaw);

        // 数据库中 token_hash 是 SHA256 哈希
        $this->assertEquals(hash('sha256', $rawToken), $token->getRawOriginal('token_hash'));

        // 加密值可以被解密回原始值
        $this->assertEquals($rawToken, Crypt::decryptString($dbRaw));
    }

    /**
     * 测试 getter 自动解密 token
     */
    public function test_token_getter_decrypts(): void
    {
        $rawToken = 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4';
        $token = $this->createDeployToken(['token' => $rawToken]);

        // 从数据库重新加载
        $token->refresh();

        $this->assertEquals($rawToken, $token->token);
    }

    /**
     * 测试 token 为空时 setter 将属性设为 null
     */
    public function test_empty_token_sets_null_attributes(): void
    {
        $token = new DeployToken;
        $token->token = '';

        $this->assertNull($token->getAttributes()['token']);
        $this->assertNull($token->getAttributes()['token_hash']);
    }

    /**
     * 测试 getter 处理无法解密的数据返回 null
     */
    public function test_getter_returns_null_on_decrypt_failure(): void
    {
        $token = $this->createDeployToken();

        // 直接写入无效的加密数据
        DeployToken::where('id', $token->id)->update(['token' => 'invalid_encrypted_data']);
        $token->refresh();

        $this->assertNull($token->token);
    }

    /**
     * 测试 findByToken 通过原始 token 查找记录
     */
    public function test_find_by_token(): void
    {
        $rawToken = 'x1y2z3w4x1y2z3w4x1y2z3w4x1y2z3w4';
        $this->createDeployToken(['token' => $rawToken]);

        $found = DeployToken::findByToken($rawToken);
        $this->assertNotNull($found);
        $this->assertEquals($rawToken, $found->token);
    }

    /**
     * 测试 findByToken 查找不存在的 token 返回 null
     */
    public function test_find_by_token_returns_null_for_unknown(): void
    {
        $this->createDeployToken();

        $found = DeployToken::findByToken('nonexistent_token_value_32chars0');
        $this->assertNull($found);
    }

    /**
     * 测试 deleteTokenByToken 删除指定 token
     */
    public function test_delete_token_by_token(): void
    {
        $rawToken = 'd1e2f3a4d1e2f3a4d1e2f3a4d1e2f3a4';
        $token = $this->createDeployToken(['token' => $rawToken]);
        $tokenId = $token->id;

        DeployToken::deleteTokenByToken($rawToken);

        $this->assertNull(DeployToken::find($tokenId));
    }

    /**
     * 测试 toArray 包含解密后的 token，不包含 token_hash
     */
    public function test_to_array_includes_token_excludes_hash(): void
    {
        $rawToken = 'b1c2d3e4b1c2d3e4b1c2d3e4b1c2d3e4';
        $token = $this->createDeployToken(['token' => $rawToken]);
        $token->refresh();

        $array = $token->toArray();

        $this->assertArrayHasKey('token', $array);
        $this->assertEquals($rawToken, $array['token']);
        $this->assertArrayNotHasKey('token_hash', $array);
    }

    /**
     * 测试每次加密产生不同密文（随机 IV），但 token_hash 相同
     */
    public function test_encryption_produces_different_ciphertext(): void
    {
        $rawToken = 'f1e2d3c4f1e2d3c4f1e2d3c4f1e2d3c4';
        $token1 = $this->createDeployToken(['token' => $rawToken]);
        $cipher1 = $token1->getRawOriginal('token');

        // 重新设置同一个 token
        $token1->token = $rawToken;
        $token1->save();
        $token1->refresh();
        $cipher2 = $token1->getRawOriginal('token');

        // 密文不同但都能解密为同一个值
        $this->assertNotEquals($cipher1, $cipher2);
        $this->assertEquals(Crypt::decryptString($cipher1), Crypt::decryptString($cipher2));
    }
}
