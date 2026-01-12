<?php

namespace App\Console\Commands;

use App\Services\Upgrade\BackupManager;
use App\Services\Upgrade\UpgradeService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

/**
 * 回滚命令
 *
 * 使用方法：
 * php artisan upgrade:rollback                    # 显示备份列表
 * php artisan upgrade:rollback 2025-01-01_120000  # 回滚到指定备份
 * php artisan upgrade:rollback --force            # 强制回滚（跳过确认）
 */
class UpgradeRollbackCommand extends Command
{
    protected $signature = 'upgrade:rollback
        {backup_id? : 备份ID，不指定则显示备份列表}
        {--force : 强制回滚，跳过确认}
        {--delete : 删除指定备份}';

    protected $description = '回滚到指定备份';

    public function __construct(
        protected BackupManager $backupManager,
        protected UpgradeService $upgradeService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $backupId = $this->argument('backup_id');
        $force = $this->option('force');
        $delete = $this->option('delete');

        // 获取备份列表（命令行显示全部）
        $backups = $this->backupManager->listBackups(0);

        if (empty($backups)) {
            $this->warn('没有可用的备份');

            return CommandAlias::SUCCESS;
        }

        // 如果没有指定备份ID，显示备份列表
        if (! $backupId) {
            return $this->showBackupList($backups);
        }

        // 删除备份
        if ($delete) {
            return $this->deleteBackup($backupId, $force);
        }

        // 执行回滚
        return $this->executeRollback($backupId, $force);
    }

    protected function showBackupList(array $backups): int
    {
        $this->info('可用备份列表:');
        $this->newLine();

        $headers = ['ID', '版本', '创建时间', '大小'];
        $rows = [];

        foreach ($backups as $backup) {
            $rows[] = [
                $backup['id'],
                $backup['version'] ?? 'unknown',
                $backup['created_at'] ?? '',
                $this->formatBytes($backup['size'] ?? 0),
            ];
        }

        $this->table($headers, $rows);

        $this->newLine();
        $this->line('使用方法:');
        $this->line('  回滚: php artisan upgrade:rollback <backup_id>');
        $this->line('  删除: php artisan upgrade:rollback <backup_id> --delete');

        return CommandAlias::SUCCESS;
    }

    protected function deleteBackup(string $backupId, bool $force): int
    {
        $backup = $this->backupManager->getBackup($backupId);

        if (! $backup) {
            $this->error("备份不存在: $backupId");

            return CommandAlias::FAILURE;
        }

        if (! $force) {
            $this->warn("即将删除备份: $backupId");
            $this->line("版本: " . ($backup['version'] ?? 'unknown'));
            $this->line("创建时间: " . ($backup['created_at'] ?? ''));

            if (! $this->confirm('确定要删除此备份吗？')) {
                $this->info('操作已取消');

                return CommandAlias::SUCCESS;
            }
        }

        if ($this->backupManager->deleteBackup($backupId)) {
            $this->info("备份已删除: $backupId");

            return CommandAlias::SUCCESS;
        } else {
            $this->error('删除备份失败');

            return CommandAlias::FAILURE;
        }
    }

    protected function executeRollback(string $backupId, bool $force): int
    {
        $backup = $this->backupManager->getBackup($backupId);

        if (! $backup) {
            $this->error("备份不存在: $backupId");

            return CommandAlias::FAILURE;
        }

        $this->info("备份信息:");
        $this->line("  ID: $backupId");
        $this->line("  版本: " . ($backup['version'] ?? 'unknown'));
        $this->line("  创建时间: " . ($backup['created_at'] ?? ''));
        $this->newLine();

        if (! $force) {
            $this->warn('回滚过程中系统将进入维护模式');
            $this->warn('当前数据将被覆盖，请确保已备份最新数据');

            if (! $this->confirm('确定要回滚到此备份吗？')) {
                $this->info('回滚已取消');

                return CommandAlias::SUCCESS;
            }
        }

        $this->newLine();
        $this->info('开始回滚...');

        $result = $this->upgradeService->rollback($backupId);

        if ($result['success']) {
            $this->newLine();
            $this->info('回滚成功！');
            $this->line("已恢复到版本: " . ($result['restored_version'] ?? 'unknown'));

            return CommandAlias::SUCCESS;
        } else {
            $this->newLine();
            $this->error('回滚失败: ' . ($result['error'] ?? '未知错误'));

            return CommandAlias::FAILURE;
        }
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $index = 0;

        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }

        return round($bytes, 2) . ' ' . $units[$index];
    }
}
