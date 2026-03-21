<?php

namespace App\Services\UserData;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class UserDataPurger
{
    private OutputInterface $output;

    private int $chunkSize;

    public function __construct(OutputInterface $output, int $chunkSize = 1000)
    {
        $this->output = $output;
        $this->chunkSize = $chunkSize;
    }

    /**
     * 清理用户所有数据
     *
     * @throws Throwable
     */
    public function purge(User $user): void
    {
        $this->output->writeln("分批大小：{$this->chunkSize} 条/批");

        // 按顺序删除
        foreach (UserDataTableRegistry::purgeOrder() as $item) {
            match ($item['type']) {
                'notification' => $this->deleteNotifications($user),
                'indirect' => $this->deleteIndirectTable($item['table'], $item['name'], $user),
                'direct' => $this->deleteDirectTable($item['table'], $item['name'], $user->id),
            };
        }

        // 动态表
        foreach (UserDataTableRegistry::dynamicTables($user->id) as $item) {
            $this->deleteDirectTable($item['table'], $item['name'], $user->id);
        }

        // 最后删除用户
        $this->output->writeln('删除用户账户...');
        DB::transaction(fn () => $user->delete());
    }

    /**
     * 分批删除直接关联表数据
     *
     * @throws Throwable
     */
    private function deleteDirectTable(string $table, string $name, int $userId): void
    {
        if (! DB::getSchemaBuilder()->hasTable($table)) {
            return;
        }

        $count = DB::table($table)->where('user_id', $userId)->count();
        if ($count === 0) {
            return;
        }

        $this->output->writeln("删除$name ($count 条)...");
        $this->deleteInChunks(
            fn () => DB::table($table)->where('user_id', $userId)->limit($this->chunkSize)->delete(),
            fn () => DB::table($table)->where('user_id', $userId)->count(),
            $count,
            $name
        );
    }

    /**
     * 分批删除间接关联表数据（通过 order_id → orders）
     *
     * @throws Throwable
     */
    private function deleteIndirectTable(string $table, string $name, User $user): void
    {
        if (! DB::getSchemaBuilder()->hasTable($table)) {
            return;
        }

        $orderSubQuery = fn ($q) => $q->select('id')->from('orders')->where('user_id', $user->id);

        $count = DB::table($table)->whereIn('order_id', $orderSubQuery)->count();
        if ($count === 0) {
            return;
        }

        $this->output->writeln("删除$name ($count 条)...");
        $this->deleteInChunks(
            fn () => DB::table($table)->whereIn('order_id', $orderSubQuery)->limit($this->chunkSize)->delete(),
            fn () => DB::table($table)->whereIn('order_id', $orderSubQuery)->count(),
            $count,
            $name
        );
    }

    /**
     * 删除通知（多态关联）
     *
     * @throws Throwable
     */
    private function deleteNotifications(User $user): void
    {
        if (! DB::getSchemaBuilder()->hasTable('notifications')) {
            return;
        }

        $count = DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->count();

        if ($count === 0) {
            return;
        }

        $this->output->writeln("删除通知 ($count 条)...");
        $this->deleteInChunks(
            fn () => DB::table('notifications')
                ->where('notifiable_type', User::class)
                ->where('notifiable_id', $user->id)
                ->limit($this->chunkSize)
                ->delete(),
            fn () => DB::table('notifications')
                ->where('notifiable_type', User::class)
                ->where('notifiable_id', $user->id)
                ->count(),
            $count,
            '通知'
        );
    }

    /**
     * 通用分批删除逻辑
     *
     * @throws Throwable
     */
    private function deleteInChunks(callable $deleteAction, callable $remainingCount, int $total, string $name): void
    {
        $totalDeleted = 0;
        $maxIterations = (int) ceil($total / $this->chunkSize) + 10;
        $iteration = 0;

        do {
            $iteration++;

            if ($iteration > $maxIterations) {
                $remaining = $remainingCount();
                $this->output->writeln("<error>警告：{$name}删除循环超过预期次数！还剩 $remaining 条数据未删除</error>");
                break;
            }

            $deleted = DB::transaction(fn () => $deleteAction());
            $totalDeleted += $deleted;

            gc_collect_cycles();
        } while ($deleted > 0);

        // 验证
        $remaining = $remainingCount();
        if ($remaining > 0) {
            $this->output->writeln("<comment>警告：{$name}还有 $remaining 条数据未删除</comment>");
        }
    }
}
