<?php

namespace App\Console\Commands;

use App\Services\Upgrade\UpgradeService;
use App\Services\Upgrade\VersionManager;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

/**
 * 检查更新命令
 *
 * 使用方法：
 * php artisan upgrade:check
 */
class UpgradeCheckCommand extends Command
{
    protected $signature = 'upgrade:check';

    protected $description = '检查系统更新';

    public function __construct(
        protected VersionManager $versionManager,
        protected UpgradeService $upgradeService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('检查更新中...');
        $this->newLine();

        // 显示当前版本
        $current = $this->versionManager->getCurrentVersion();
        $this->line("当前版本: <fg=cyan>{$current['version']}</>");
        $this->line("发布通道: <fg=cyan>{$current['channel']}</>");

        if ($current['build_time']) {
            $this->line("构建时间: <fg=cyan>{$current['build_time']}</>");
        }

        $this->newLine();

        // 检查更新
        $result = $this->upgradeService->checkForUpdate();

        if (! $result['latest_version']) {
            $this->warn('无法获取最新版本信息，请检查网络连接');

            return CommandAlias::FAILURE;
        }

        if ($result['has_update']) {
            $this->info("发现新版本: <fg=green>{$result['latest_version']}</>");
            $this->newLine();

            if ($result['release_date']) {
                $this->line("发布时间: {$result['release_date']}");
            }

            if ($result['package_size'] && $result['package_size'] !== 'unknown') {
                $this->line("升级包大小: {$result['package_size']}");
            }

            if ($result['changelog']) {
                $this->newLine();
                $this->line('<fg=yellow>更新日志:</>');
                $this->line($result['changelog']);
            }

            $this->newLine();
            $this->info('运行 <fg=cyan>php artisan upgrade:run</> 进行升级');
        } else {
            $this->info('当前已是最新版本');
        }

        return CommandAlias::SUCCESS;
    }
}
