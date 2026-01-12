<?php

namespace App\Services\Upgrade;

use Illuminate\Support\Facades\Log;

class UpgradeStatusManager
{
    protected string $statusFile;

    public function __construct()
    {
        $this->statusFile = storage_path('upgrades/status.json');
        $this->ensureDirectory();
    }

    protected function ensureDirectory(): void
    {
        $dir = dirname($this->statusFile);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * 开始升级
     */
    public function start(string $version): void
    {
        $this->save([
            'status' => 'running',
            'version' => $version,
            'started_at' => date('Y-m-d H:i:s'),
            'current_step' => null,
            'steps' => [],
            'progress' => 0,
            'error' => null,
        ]);
    }

    /**
     * 更新当前步骤
     */
    public function updateStep(string $step, string $status, ?string $error = null): void
    {
        $data = $this->get();
        if (! $data) {
            return;
        }

        $data['current_step'] = $step;

        // 更新或添加步骤
        $found = false;
        foreach ($data['steps'] as &$s) {
            if ($s['step'] === $step) {
                $s['status'] = $status;
                if ($error) {
                    $s['error'] = $error;
                }
                $found = true;
                break;
            }
        }

        if (! $found) {
            $stepData = ['step' => $step, 'status' => $status];
            if ($error) {
                $stepData['error'] = $error;
            }
            $data['steps'][] = $stepData;
        }

        // 计算进度
        $totalSteps = $this->getTotalSteps();
        $completedSteps = count(array_filter($data['steps'], fn ($s) => $s['status'] === 'completed'));
        $data['progress'] = $totalSteps > 0 ? round(($completedSteps / $totalSteps) * 100) : 0;

        $this->save($data);
    }

    /**
     * 标记步骤完成
     */
    public function completeStep(string $step): void
    {
        $this->updateStep($step, 'completed');
    }

    /**
     * 标记步骤开始
     */
    public function startStep(string $step): void
    {
        $this->updateStep($step, 'running');
    }

    /**
     * 标记步骤失败
     */
    public function failStep(string $step, string $error): void
    {
        $this->updateStep($step, 'failed', $error);
    }

    /**
     * 标记升级完成
     */
    public function complete(string $fromVersion, string $toVersion): void
    {
        $data = $this->get();
        if ($data) {
            $data['status'] = 'completed';
            $data['completed_at'] = date('Y-m-d H:i:s');
            $data['from_version'] = $fromVersion;
            $data['to_version'] = $toVersion;
            $data['progress'] = 100;
            $this->save($data);
        }
    }

    /**
     * 标记升级失败
     */
    public function fail(string $error): void
    {
        $data = $this->get();
        if ($data) {
            $data['status'] = 'failed';
            $data['error'] = $error;
            $data['failed_at'] = date('Y-m-d H:i:s');
            $this->save($data);
        }

        Log::error("升级失败: $error");
    }

    /**
     * 获取当前状态
     */
    public function get(): ?array
    {
        if (! file_exists($this->statusFile)) {
            return null;
        }

        $content = file_get_contents($this->statusFile);

        return json_decode($content, true);
    }

    /**
     * 检查是否正在升级
     */
    public function isRunning(): bool
    {
        $data = $this->get();

        return $data && $data['status'] === 'running';
    }

    /**
     * 清除状态
     */
    public function clear(): void
    {
        if (file_exists($this->statusFile)) {
            unlink($this->statusFile);
        }
    }

    /**
     * 保存状态
     */
    protected function save(array $data): void
    {
        file_put_contents(
            $this->statusFile,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * 获取总步骤数（预估）
     */
    protected function getTotalSteps(): int
    {
        // 基本步骤：fetch_release, check_version, backup, download, extract, apply, clear_cache, update_version, cleanup
        return 9;
    }
}
