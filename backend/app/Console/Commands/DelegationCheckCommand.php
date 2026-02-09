<?php

namespace App\Console\Commands;

use App\Models\Cert;
use App\Models\CnameDelegation;
use App\Services\Delegation\CnameDelegationService;
use Illuminate\Console\Command;
use Throwable;

/**
 * CNAME 委托健康检查命令（手工运行）
 *
 * 用法：php artisan delegation:check [--dry-run]
 *
 * 检查所有委托记录的 CNAME 配置是否有效：
 * - 有效：保留
 * - 无效：检查是否有有效证书使用该域名（active/unpaid/pending/processing/approving）
 *   - 有有效证书：保留委托记录（用户可能需要修复）
 *   - 无有效证书：删除委托记录（已无用）
 */
class DelegationCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delegation:check {--dry-run : 只检查不删除} {--check-txt : 检测TXT冲突}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check CNAME delegation health and cleanup unused invalid delegations';

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
        $dryRun = $this->option('dry-run');
        $checkTxt = $this->option('check-txt');
        $this->info('开始检查 CNAME 委托健康状态...'.($dryRun ? ' (dry-run 模式)' : '').($checkTxt ? ' (含TXT冲突检测)' : ''));

        $totalCount = 0;
        $validCount = 0;
        $invalidKeepCount = 0;
        $deletedCount = 0;
        $errorCount = 0;

        // 批量处理委托记录
        CnameDelegation::query()->chunkById(200, function ($delegations) use ($dryRun, $checkTxt, &$totalCount, &$validCount, &$invalidKeepCount, &$deletedCount, &$errorCount) {
            foreach ($delegations as $delegation) {
                $totalCount++;

                try {
                    $isValid = $this->delegationService->checkAndUpdateValidity($delegation);

                    // 检测 TXT 冲突（仅在 --check-txt 时执行，避免批量查询拉长耗时）
                    if ($checkTxt) {
                        $txtWarning = $this->delegationService->checkTxtConflict($delegation);
                        if ($txtWarning) {
                            $this->warn("  ⚠ $txtWarning");
                        }
                    }

                    if ($isValid) {
                        $validCount++;
                        $this->line("✓ 委托 #$delegation->id ($delegation->zone) - 有效");
                    } else {
                        // 无效委托：检查是否有 active 证书使用该域名
                        $hasActiveCert = $this->hasActiveCertForDomain($delegation->user_id, $delegation->zone);

                        if ($hasActiveCert) {
                            // 有 active 证书使用该域名，保留委托记录
                            $invalidKeepCount++;
                            $this->warn("✗ 委托 #$delegation->id ($delegation->zone) - 无效但有 active 证书，保留");

                            // 检查失败次数，触发预警
                            if ($delegation->fail_count >= 3) {
                                $this->error("  ⚠ 连续失败 $delegation->fail_count 次，请检查 CNAME 配置");
                            }
                        } else {
                            // 无 active 证书，删除委托记录
                            $deletedCount++;
                            if ($dryRun) {
                                $this->warn("✗ 委托 #$delegation->id ($delegation->zone) - 无效且无 active 证书，将删除 (dry-run)");
                            } else {
                                $delegation->delete();
                                $this->warn("✗ 委托 #$delegation->id ($delegation->zone) - 无效且无 active 证书，已删除");
                            }
                        }
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
                ['无效(保留)', $invalidKeepCount],
                ['无效(删除)', $deletedCount],
                ['异常', $errorCount],
            ]
        );

        if ($dryRun && $deletedCount > 0) {
            $this->warn('dry-run 模式：实际未删除任何记录，移除 --dry-run 参数执行实际删除');
        }
    }

    /**
     * 检查用户是否有包含该域名的有效证书
     * 状态包括：active、unpaid、pending、processing、approving
     */
    private function hasActiveCertForDomain(int $userId, string $domain): bool
    {
        return Cert::whereIn('status', ['active', 'unpaid', 'pending', 'processing', 'approving'])
            ->whereHas('order', fn ($q) => $q->where('user_id', $userId))
            ->where(function ($query) use ($domain) {
                // 检查 common_name 或 alternative_names 是否包含该域名
                $query->where('common_name', $domain)
                    ->orWhere('alternative_names', 'like', "%$domain%");
            })
            ->exists();
    }
}
