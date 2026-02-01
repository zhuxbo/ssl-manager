<?php

namespace Tests\Unit\Models;

use App\Models\Order;
use App\Models\Product;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

#[Group('database')]
class OrderAutoFieldsTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = DatabaseSeeder::class;

    /**
     * 测试 auto_renew 正确转换为 boolean
     */
    public function test_auto_renew_casts_to_boolean(): void
    {
        $user = $this->createTestUser();
        $product = Product::factory()->create();

        $order = Order::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'brand' => 'test',
            'period' => 12,
            'auto_renew' => true,
        ]);

        $this->assertTrue($order->auto_renew);

        $order->auto_renew = false;
        $order->save();
        $order->refresh();

        $this->assertFalse($order->auto_renew);
    }

    /**
     * 测试 auto_reissue 正确转换为 boolean
     */
    public function test_auto_reissue_casts_to_boolean(): void
    {
        $user = $this->createTestUser();
        $product = Product::factory()->create();

        $order = Order::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'brand' => 'test',
            'period' => 12,
            'auto_reissue' => true,
        ]);

        $this->assertTrue($order->auto_reissue);

        $order->auto_reissue = false;
        $order->save();
        $order->refresh();

        $this->assertFalse($order->auto_reissue);
    }

    /**
     * 测试允许 null 值以便回落到用户设置
     */
    public function test_allows_null_for_fallback(): void
    {
        $user = $this->createTestUser();
        $product = Product::factory()->create();

        $order = Order::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'brand' => 'test',
            'period' => 12,
            'auto_renew' => null,
            'auto_reissue' => null,
        ]);

        $this->assertNull($order->auto_renew);
        $this->assertNull($order->auto_reissue);
    }

    /**
     * 测试整数转换为 boolean
     */
    public function test_casts_integer_to_boolean(): void
    {
        $user = $this->createTestUser();
        $product = Product::factory()->create();

        $order = Order::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'brand' => 'test',
            'period' => 12,
            'auto_renew' => 1,
            'auto_reissue' => 0,
        ]);

        $this->assertTrue($order->auto_renew);
        $this->assertFalse($order->auto_reissue);
    }

    /**
     * 测试默认创建时字段为 null
     */
    public function test_defaults_to_null_on_create(): void
    {
        $user = $this->createTestUser();
        $product = Product::factory()->create();

        $order = Order::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'brand' => 'test',
            'period' => 12,
        ]);

        $this->assertNull($order->auto_renew);
        $this->assertNull($order->auto_reissue);
    }
}
