<?php

namespace App\Services\UserData;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class UserDataPurger
{
    private OutputInterface $output;

    private int $chunkSize;

    private ?string $exportFilePath;

    public function __construct(OutputInterface $output, int $chunkSize = 1000, ?string $exportFilePath = null)
    {
        $this->output = $output;
        $this->chunkSize = $chunkSize;
        $this->exportFilePath = $exportFilePath;
    }

    /**
     * 清理用户所有数据
     *
     * @throws Throwable
     */
    public function purge(User $user): void
    {
        $this->output->writeln("分批大小：{$this->chunkSize} 条/批");

        // 清理文档文件（在删除数据库记录之前）
        $this->deleteDocumentFiles($user->id);

        // 按顺序删除
        foreach (UserDataTableRegistry::purgeOrder() as $item) {
            match ($item['type']) {
                'notification' => $this->deleteNotifications($user),
                'indirect' => $this->deleteIndirectTable($item['table'], $item['name'], $user),
                'direct' => $this->deleteDirectTable($item['table'], $item['name'], $user->id),
            };
        }

        // 清理孤立的间接关联数据（导入失败导致 orders 缺失时，certs 等无法通过子查询删除）
        if ($this->exportFilePath) {
            $this->cleanupOrphans();
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
     * 清理孤立的间接关联数据
     * 通过导出文件中记录的雪花 ID 直接定位并删除
     *
     * @throws Throwable
     */
    private function cleanupOrphans(): void
    {
        $importer = new UserDataImporter($this->output);
        $tableIds = $importer->extractTableIds($this->exportFilePath);

        foreach (UserDataTableRegistry::indirectTables() as $item) {
            $ids = $tableIds[$item['table']] ?? [];
            if (empty($ids)) {
                continue;
            }

            if (! DB::getSchemaBuilder()->hasTable($item['table'])) {
                continue;
            }

            // 查找仍然残留的记录
            $remaining = [];
            foreach (array_chunk($ids, 1000) as $chunk) {
                $found = DB::table($item['table'])->whereIn('id', $chunk)->pluck('id')->all();
                $remaining = array_merge($remaining, $found);
            }

            if (empty($remaining)) {
                continue;
            }

            $this->output->writeln("清理孤立{$item['name']} (".count($remaining).' 条)...');
            foreach (array_chunk($remaining, $this->chunkSize) as $chunk) {
                DB::transaction(fn () => DB::table($item['table'])->whereIn('id', $chunk)->delete());
            }
        }
    }

    /**
     * 清理 order_documents 关联的物理文件
     */
    private function deleteDocumentFiles(int $userId): void
    {
        if (! DB::getSchemaBuilder()->hasTable('order_documents')) {
            return;
        }

        $orderIds = DB::table('order_documents')
            ->where('user_id', $userId)
            ->distinct()
            ->pluck('order_id');

        if ($orderIds->isEmpty()) {
            return;
        }

        $fileCount = 0;
        foreach ($orderIds as $orderId) {
            $dir = "verification/$orderId";
            if (Storage::exists($dir)) {
                Storage::deleteDirectory($dir);
                $fileCount++;
            }
        }

        if ($fileCount > 0) {
            $this->output->writeln("清理文档文件 ($fileCount 个目录)...");
        }
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
