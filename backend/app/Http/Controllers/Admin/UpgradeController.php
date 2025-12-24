<?php

namespace App\Http\Controllers\Admin;

use App\Services\Upgrade\UpgradeService;
use App\Services\Upgrade\VersionManager;
use Illuminate\Http\Request;

class UpgradeController extends BaseController
{
    public function __construct(
        protected VersionManager $versionManager,
        protected UpgradeService $upgradeService,
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
        $limit = $request->input('limit', 10);
        $releases = $this->upgradeService->getReleaseHistory($limit);

        $this->success([
            'releases' => $releases,
            'current_version' => $this->versionManager->getVersionString(),
        ]);
    }

    /**
     * 执行升级
     */
    public function execute(Request $request): void
    {
        $version = $request->input('version', 'latest');

        $result = $this->upgradeService->performUpgrade($version);

        if ($result['success']) {
            $this->success($result);
        } else {
            $this->error($result['error'] ?? '升级失败', ['steps' => $result['steps'] ?? []]);
        }
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
}
