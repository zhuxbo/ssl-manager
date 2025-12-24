<?php

namespace App\Console\Commands;

use App\Services\Upgrade\UpgradeService;
use App\Services\Upgrade\VersionManager;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

/**
 * 执行升级命令
 *
 * 使用方法：
 * php artisan upgrade:run             # 升级到最新版本
 * php artisan upgrade:run 1.1.0       # 升级到指定版本
 * php artisan upgrade:run --force     # 强制升级（跳过确认）
 */
class UpgradeRunCommand extends Command
{
    protected $signature = 'upgrade:run
        {version=latest : 目标版本号，默认为 latest}
        {--force : 强制升级，跳过确认}
        {--no-backup : 跳过备份}';

    protected $description = '执行系统升级';

    public function __construct(
        protected VersionManager $versionManager,
        protected UpgradeService $upgradeService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $version = $this->argument('version');
        $force = $this->option('force');

        // 显示当前版本
        $current = $this->versionManager->getCurrentVersion();
        $this->info("当前版本: {$current['version']}");
        $this->newLine();

        // 检查 PHP 版本
        if (! $this->versionManager->checkPhpVersion()) {
            $minVersion = $this->versionManager->getMinPhpVersion();
            $this->error("PHP 版本不满足要求");
            $this->line("当前版本: " . PHP_VERSION);
            $this->line("最低要求: $minVersion");

            return CommandAlias::FAILURE;
        }

        // 获取目标版本信息
        $this->info('获取版本信息...');
        $checkResult = $this->upgradeService->checkForUpdate();

        if (! $checkResult['latest_version'] && $version === 'latest') {
            $this->error('无法获取最新版本信息');

            return CommandAlias::FAILURE;
        }

        $targetVersion = $version === 'latest' ? $checkResult['latest_version'] : $version;

        if (! $this->versionManager->isUpgradeAllowed($targetVersion)) {
            $this->warn("目标版本 $targetVersion 不允许升级（版本相同或低于当前版本）");

            return CommandAlias::FAILURE;
        }

        $this->info("目标版本: $targetVersion");
        $this->newLine();

        // 确认升级
        if (! $force) {
            $this->warn('升级过程中系统将进入维护模式，请确保已备份重要数据');

            if (! $this->confirm("确定要升级到版本 $targetVersion 吗？")) {
                $this->info('升级已取消');

                return CommandAlias::SUCCESS;
            }
        }

        $this->newLine();
        $this->info('开始升级...');
        $this->newLine();

        // 执行升级
        $result = $this->upgradeService->performUpgrade($version);

        // 显示步骤结果
        $steps = $result['steps'] ?? [];
        $stepNames = [
            'fetch_release' => '获取版本信息',
            'backup' => '创建备份',
            'maintenance_on' => '进入维护模式',
            'download' => '下载升级包',
            'extract' => '解压升级包',
            'apply' => '应用升级',
            'migrate' => '运行数据库迁移',
            'update_version' => '更新版本号',
            'clear_cache' => '清理缓存',
            'cleanup' => '清理临时文件',
            'maintenance_off' => '退出维护模式',
        ];

        foreach ($steps as $step) {
            $name = $stepNames[$step['step']] ?? $step['step'];
            $status = $step['status'];

            if ($status === 'completed') {
                $this->line("  <fg=green>✓</> $name");
            } elseif ($status === 'failed') {
                $this->line("  <fg=red>✗</> $name");
                if (isset($step['error'])) {
                    $this->line("    <fg=red>{$step['error']}</>");
                }
            } else {
                $this->line("  <fg=yellow>○</> $name");
            }
        }

        $this->newLine();

        if ($result['success']) {
            $this->info("升级成功！");
            $this->line("版本: {$result['from_version']} -> {$result['to_version']}");

            if (isset($result['backup_id'])) {
                $this->line("备份ID: {$result['backup_id']}");
            }

            return CommandAlias::SUCCESS;
        } else {
            $this->error("升级失败: " . ($result['error'] ?? '未知错误'));
            $this->newLine();
            $this->warn('如果系统异常，可以使用以下命令回滚：');
            $this->line('  php artisan upgrade:rollback <backup_id>');

            return CommandAlias::FAILURE;
        }
    }
}
