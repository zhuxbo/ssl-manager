<?php

namespace App\Console\Commands;

use App\Services\Upgrade\UpgradeService;
use App\Services\Upgrade\UpgradeStatusManager;
use Illuminate\Console\Command;

class UpgradeRunCommand extends Command
{
    protected $signature = 'upgrade:run {version=latest}';

    protected $description = '执行系统升级';

    public function handle(UpgradeService $upgradeService, UpgradeStatusManager $statusManager): int
    {
        $version = $this->argument('version');

        $this->info("开始升级到版本: $version");

        // 标记升级开始
        $statusManager->start($version);

        try {
            // 执行升级，传入状态管理器用于实时更新进度
            $result = $upgradeService->performUpgradeWithStatus($version, $statusManager);

            if ($result['success']) {
                $this->info('升级成功！');

                return Command::SUCCESS;
            } else {
                $this->error('升级失败: '.($result['error'] ?? '未知错误'));

                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $statusManager->fail($e->getMessage());
            $this->error('升级异常: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
