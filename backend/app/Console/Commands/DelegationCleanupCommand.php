<?php

namespace App\Console\Commands;

use App\Models\CnameDelegation;
use App\Models\Order;
use App\Services\Delegation\DelegationDnsService;
use App\Services\Delegation\ProxyDNS;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class DelegationCleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delegation:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '清理不是processing状态订单的委托DNS记录';

    protected DelegationDnsService $dnsService;

    protected ProxyDNS $proxyDNS;

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->dnsService = app(DelegationDnsService::class);
        $this->proxyDNS = app(ProxyDNS::class);

        $this->info('开始清理委托DNS记录...');

        try {
            // 1. 获取委托域名配置
            $delegation = get_system_setting('site', 'delegation');
            $proxyZone = $delegation['proxyZone'] ?? null;

            if (empty($proxyZone)) {
                $this->error('代理域名未设置，无法执行清理');

                return;
            }

            $this->info("委托域名: $proxyZone");

            // 2. 调用腾讯云 API 查询委托域名的所有 TXT 记录
            $this->info('正在查询腾讯云 TXT 记录...');
            $allTxtRecords = $this->proxyDNS->getAllTxtRecords($proxyZone);
            $this->info('腾讯云 TXT 记录总数: '.count($allTxtRecords));

            // 3. 查询所有处理中订单的委托记录
            $this->info('正在查询处理中订单的委托记录...');
            $processingOrders = Order::with(['latestCert'])
                ->whereHas('latestCert', function ($query) {
                    $query->where('status', 'processing');
                })
                ->get();

            // 4. 收集所有应该保留的 label（记录名）
            $keepLabels = collect();
            foreach ($processingOrders as $order) {
                $cert = $order->latestCert;
                $validations = $cert->validation;

                if (empty($validations) || ! is_array($validations)) {
                    continue;
                }

                foreach ($validations as $validation) {
                    if (isset($validation['delegation_id'])) {
                        $delegation = CnameDelegation::find($validation['delegation_id']);
                        if ($delegation) {
                            $keepLabels->push($delegation->label);
                        }
                    }
                }
            }

            $keepLabels = $keepLabels->unique()->values();
            $this->info('应该保留的记录数: '.$keepLabels->count());

            // 5. 比对差值，找出需要删除的记录
            $recordsToDelete = collect($allTxtRecords)->filter(function ($record) use ($keepLabels) {
                return ! $keepLabels->contains($record['name']);
            });

            $this->info('需要删除的记录数: '.$recordsToDelete->count());

            if ($recordsToDelete->isEmpty()) {
                $this->info('没有需要清理的记录');

                return;
            }

            // 6. 批量删除记录（一次最多 2000 条）
            $recordIds = $recordsToDelete->pluck('id')->toArray();
            $this->info('正在批量删除记录...');
            $this->proxyDNS->batchDeleteRecords($recordIds);

            Log::info('批量清理委托DNS记录成功', [
                'proxy_zone' => $proxyZone,
                'deleted_count' => count($recordIds),
                'deleted_labels' => $recordsToDelete->pluck('name')->toArray(),
            ]);

            // 7. 清理数据库中的 auto_txt_written 标记
            $this->info('正在清理数据库中的标记...');
            $cleanedMarkCount = $this->cleanDatabaseMarks($recordsToDelete->pluck('name')->toArray());

            $this->info('清理完成！');
            $this->info('- 删除了 '.count($recordIds).' 条 DNS 记录');
            $this->info("- 清理了 $cleanedMarkCount 个数据库标记");
        } catch (Throwable $e) {
            $this->error('清理失败: '.$e->getMessage());
            Log::error('委托DNS记录清理失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * 清理数据库中的 auto_txt_written 标记
     *
     * @param  array  $deletedLabels  已删除的 label 数组
     * @return int 清理的标记数量
     */
    protected function cleanDatabaseMarks(array $deletedLabels): int
    {
        $cleanedCount = 0;

        // 查找所有设置了auto_txt_written标记的证书
        $orders = Order::with(['latestCert'])
            ->whereHas('latestCert', function ($query) {
                $query->where('created_at', '>=', now()->subDays(30));
            })
            ->get();

        foreach ($orders as $order) {
            $cert = $order->latestCert;
            $validations = $cert->validation;

            if (empty($validations) || ! is_array($validations)) {
                continue;
            }

            $hasChanges = false;
            $updatedValidations = [];

            foreach ($validations as $index => $validation) {
                // 检查是否有auto_txt_written标记
                if (! isset($validation['auto_txt_written']) || $validation['auto_txt_written'] !== true) {
                    $updatedValidations[$index] = $validation;

                    continue;
                }

                $delegationId = $validation['delegation_id'] ?? null;

                if (! $delegationId) {
                    $updatedValidations[$index] = $validation;

                    continue;
                }

                // 获取委托记录
                $delegation = CnameDelegation::find($delegationId);

                // 如果委托记录不存在，或者 label 已被删除，清理标记
                if (! $delegation || in_array($delegation->label, $deletedLabels)) {
                    unset($validation['auto_txt_written']);
                    unset($validation['auto_txt_written_at']);
                    unset($validation['delegation_id']);
                    $updatedValidations[$index] = $validation;
                    $hasChanges = true;
                    $cleanedCount++;
                } else {
                    $updatedValidations[$index] = $validation;
                }
            }

            // 保存更新后的validation
            if ($hasChanges) {
                $cert->validation = $updatedValidations;
                $cert->save();
            }
        }

        return $cleanedCount;
    }
}
