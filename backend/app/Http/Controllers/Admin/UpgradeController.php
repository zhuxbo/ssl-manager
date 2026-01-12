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

        // 清理旧的状态文件
        $this->statusManager->clear();

        // 启动后台升级进程，输出重定向到日志文件
        $phpBinary = $this->findPhpBinary();
        $artisan = base_path('artisan');
        $logFile = storage_path('logs/upgrade-process.log');

        $command = sprintf(
            '%s %s upgrade:run %s >> %s 2>&1 &',
            escapeshellarg($phpBinary),
            escapeshellarg($artisan),
            escapeshellarg($version),
            escapeshellarg($logFile)
        );

        Log::info("[Upgrade] 启动升级任务: $version", [
            'command' => $command,
            'log_file' => $logFile,
        ]);

        exec($command);

        // 等待并检查进程是否成功启动
        // 多次重试，最多等待 3 秒
        $maxRetries = 6;
        $status = null;

        for ($i = 0; $i < $maxRetries; $i++) {
            usleep(500000); // 0.5秒

            $status = $this->statusManager->get();
            if ($status) {
                break;
            }
        }

        // 检查状态文件是否已创建
        if (! $status) {
            Log::error('[Upgrade] 状态文件未创建，进程启动失败', [
                'command' => $command,
                'log_file' => $logFile,
            ]);

            // 检查日志文件是否有错误信息
            $errorMessage = '升级进程启动失败';
            if (file_exists($logFile)) {
                $logContent = file_get_contents($logFile);
                if (! empty($logContent)) {
                    // 获取最后几行日志
                    $lines = array_slice(explode("\n", trim($logContent)), -5);
                    $errorMessage .= '：' . implode(' ', $lines);
                }
            }

            $this->error($errorMessage);

            return;
        }

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

    /**
     * 查找 PHP CLI 二进制路径
     * PHP_BINARY 在 PHP-FPM 环境下返回 php-fpm 路径，需要找到 php CLI
     */
    protected function findPhpBinary(): string
    {
        // 如果 PHP_BINARY 不是 php-fpm，直接使用
        if (! str_contains(PHP_BINARY, 'fpm')) {
            return PHP_BINARY;
        }

        // 尝试常见的 PHP CLI 路径（最低支持 PHP 8.3）
        $candidates = [
            '/usr/bin/php',
            '/usr/local/bin/php',
            '/opt/php/bin/php',
            '/www/server/php/83/bin/php',  // 宝塔 PHP 8.3
            '/www/server/php/84/bin/php',  // 宝塔 PHP 8.4
        ];

        foreach ($candidates as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        // 尝试从 PATH 中查找
        $output = [];
        exec('which php 2>/dev/null', $output);
        if (! empty($output[0]) && file_exists($output[0])) {
            return $output[0];
        }

        // 回退：尝试从 php-fpm 路径推断 php 路径
        $phpFpmPath = PHP_BINARY;
        $phpPath = str_replace(['php-fpm', 'sbin'], ['php', 'bin'], $phpFpmPath);
        if ($phpPath !== $phpFpmPath && file_exists($phpPath)) {
            return $phpPath;
        }

        // 最后尝试直接使用 'php' 命令
        return 'php';
    }
}
