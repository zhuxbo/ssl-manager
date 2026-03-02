<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 任务工厂
 *
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'action' => 'new',
            'result' => null,
            'attempts' => 0,
            'source' => 'system',
            'weight' => 0,
            'status' => 'executing',
        ];
    }

    /**
     * 处理中
     */
    public function processing(): static
    {
        return $this->state([
            'status' => 'executing',
            'started_at' => now(),
            'attempts' => 1,
        ]);
    }

    /**
     * 已完成
     */
    public function completed(): static
    {
        return $this->state([
            'status' => 'successful',
            'started_at' => now()->subMinutes(5),
            'last_execute_at' => now(),
            'attempts' => 1,
            'result' => ['success' => true],
        ]);
    }

    /**
     * 已失败
     */
    public function failed(): static
    {
        return $this->state([
            'status' => 'failed',
            'started_at' => now()->subMinutes(5),
            'last_execute_at' => now(),
            'attempts' => 3,
            'result' => ['error' => 'Task execution failed'],
        ]);
    }

    /**
     * 重签操作
     */
    public function reissue(): static
    {
        return $this->state(['action' => 'reissue']);
    }

    /**
     * 续费操作
     */
    public function renew(): static
    {
        return $this->state(['action' => 'renew']);
    }

    /**
     * 吊销操作
     */
    public function revoke(): static
    {
        return $this->state(['action' => 'revoke']);
    }
}
