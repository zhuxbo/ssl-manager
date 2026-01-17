<?php

namespace App\Console\Commands;

use App\Services\Upgrade\UpgradeService;
use App\Services\Upgrade\UpgradeStatusManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpgradeRunCommand extends Command
{
    protected $signature = 'upgrade:run {version=latest}';

    protected $description = '执行系统升级';

    public function handle(UpgradeService $upgradeService, UpgradeStatusManager $statusManager): int
    {
        $version = $this->argument('version');

        Log::info("[Upgrade] 开始升级到版本: $version");
        $this->info("开始升级到版本: $version");

        // 标记升级开始
        $statusManager->start($version);

        try {
            // 执行升级，传入状态管理器用于实时更新进度
            $result = $upgradeService->performUpgradeWithStatus($version, $statusManager);

            if ($result['success']) {
                Log::info('[Upgrade] 升级成功！');
                $this->info('升级成功！');

                return Command::SUCCESS;
            } else {
                $error = $result['error'] ?? '未知错误';
                Log::error("[Upgrade] 升级失败: $error");
                $this->error("升级失败: $error");

                // 确保状态被标记为失败（performUpgradeWithStatus 内部应该已经调用了）
                // 但为了安全起见，再次确认状态
                $currentStatus = $statusManager->get();
                if ($currentStatus && $currentStatus['status'] !== 'failed') {
                    $statusManager->fail($error);
                }

                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            Log::error("[Upgrade] 升级异常: {$e->getMessage()}", [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);
            $statusManager->fail($e->getMessage());
            $this->error('升级异常: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
