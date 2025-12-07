<?php

namespace App\Console\Commands;

use App\Models\CnameDelegation;
use App\Services\Delegation\CnameDelegationService;
use Illuminate\Console\Command;
use Throwable;

/**
 * CNAME 委托健康检查命令
 * 定期检查所有委托记录的 CNAME 配置是否有效
 */
class DelegationCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delegation:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check CNAME delegation health';

    protected CnameDelegationService $delegationService;

    public function __construct()
    {
        parent::__construct();
        $this->delegationService = new CnameDelegationService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('开始检查 CNAME 委托健康状态...');

        $totalCount = 0;
        $validCount = 0;
        $invalidCount = 0;
        $errorCount = 0;

        // 批量处理委托记录
        CnameDelegation::query()->chunkById(200, function ($delegations) use (&$totalCount, &$validCount, &$invalidCount, &$errorCount) {
            foreach ($delegations as $delegation) {
                $totalCount++;

                try {
                    $isValid = $this->delegationService->checkAndUpdateValidity($delegation);

                    if ($isValid) {
                        $validCount++;
                        $this->line("✓ 委托 #$delegation->id ($delegation->zone) - 有效");
                    } else {
                        $invalidCount++;
                        $this->warn("✗ 委托 #$delegation->id ($delegation->zone) - 无效: $delegation->last_error");
                    }

                    // 检查失败次数，触发预警
                    if ($delegation->fail_count >= 3) {
                        $this->error("⚠ 委托 #$delegation->id ($delegation->zone) - 连续失败 $delegation->fail_count 次");
                        // Todo: 这里可以添加发送通知的逻辑
                    }
                } catch (Throwable $e) {
                    $errorCount++;
                    $this->error("✗ 委托 #$delegation->id ($delegation->zone) - 检查异常: {$e->getMessage()}");
                }
            }
        });

        // 输出统计信息
        $this->info("\n检查完成！");
        $this->table(
            ['统计项', '数量'],
            [
                ['总计', $totalCount],
                ['有效', $validCount],
                ['无效', $invalidCount],
                ['异常', $errorCount],
            ]
        );

        // 如果有失败的委托，返回非零退出码
        if ($invalidCount > 0 || $errorCount > 0) {
            $this->warn("存在 $invalidCount 个无效委托和 $errorCount 个检查异常");
        }
    }
}
