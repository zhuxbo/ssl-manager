<?php

namespace App\Http\Controllers\Admin;

use App\Services\Upgrade\UpgradeService;
use App\Services\Upgrade\UpgradeStatusManager;
use App\Services\Upgrade\VersionManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UpgradeController extends BaseController
{
    public function __construct(
        protected VersionManager $versionManager,
        protected UpgradeService $upgradeService,
        protected UpgradeStatusManager $statusManager,
    ) {
        parent::__construct();
    }

    /**
     * 获取当前版本信息
     */
    public function version(): void
    {
        $version = $this->versionManager->getCurrentVersion();

        $this->success($version);
    }

    /**
     * 检查更新
     */
    public function check(): void
    {
        $result = $this->upgradeService->checkForUpdate();

        $this->success($result);
    }

    /**
     * 获取历史版本列表
     */
    public function releases(Request $request): void
    {
        $limit = $request->input('limit', 5);
        $releases = $this->upgradeService->getReleaseHistory($limit);

        $this->success([
            'releases' => $releases,
            'current_version' => $this->versionManager->getVersionString(),
        ]);
    }

    /**
     * 启动升级任务（后台执行）
     */
    public function execute(Request $request): void
    {
        $version = $request->input('version', 'latest');

        // 检查是否已有升级任务在运行
        if ($this->statusManager->isRunning()) {
            $this->error('已有升级任务在运行中');

            return;
        }

        // 启动后台升级进程
        $phpBinary = PHP_BINARY;
        $artisan = base_path('artisan');
        $command = sprintf(
            '%s %s upgrade:run %s > /dev/null 2>&1 &',
            escapeshellarg($phpBinary),
            escapeshellarg($artisan),
            escapeshellarg($version)
        );

        exec($command);

        Log::info("升级任务已启动: $version");

        $this->success([
            'started' => true,
            'version' => $version,
            'message' => '升级任务已启动，请轮询状态接口获取进度',
        ]);
    }

    /**
     * 获取升级状态
     */
    public function status(): void
    {
        $status = $this->statusManager->get();

        if (! $status) {
            $this->success([
                'status' => 'idle',
                'message' => '没有进行中的升级任务',
            ]);

            return;
        }

        $this->success($status);
    }

    /**
     * 获取备份列表
     */
    public function backups(): void
    {
        $backups = $this->upgradeService->getBackups();

        $this->success([
            'backups' => $backups,
        ]);
    }

    /**
     * 执行回滚
     */
    public function rollback(Request $request): void
    {
        $backupId = $request->input('backup_id');

        if (! $backupId) {
            $this->error('请指定要恢复的备份');
        }

        $result = $this->upgradeService->rollback($backupId);

        if ($result['success']) {
            $this->success($result);
        } else {
            $this->error($result['error'] ?? '回滚失败');
        }
    }

    /**
     * 删除备份
     */
    public function deleteBackup(Request $request): void
    {
        $backupId = $request->input('backup_id');

        if (! $backupId) {
            $this->error('请指定要删除的备份');
        }

        $deleted = $this->upgradeService->deleteBackup($backupId);

        if ($deleted) {
            $this->success(['deleted' => true]);
        } else {
            $this->error('删除备份失败');
        }
    }

    /**
     * 设置发布通道
     */
    public function setChannel(Request $request): void
    {
        $channel = $request->input('channel');

        if (! in_array($channel, ['main', 'dev'])) {
            $this->error("无效的通道: $channel");
        }

        $result = $this->versionManager->setChannel($channel);

        if ($result) {
            $this->success([
                'channel' => $channel,
                'message' => '通道已切换',
            ]);
        } else {
            $this->error('切换通道失败');
        }
    }
}
