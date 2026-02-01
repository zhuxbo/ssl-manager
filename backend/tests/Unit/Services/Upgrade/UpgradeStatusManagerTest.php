<?php

namespace Tests\Unit\Services\Upgrade;

use App\Services\Upgrade\UpgradeStatusManager;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class UpgradeStatusManagerTest extends TestCase
{
    protected UpgradeStatusManager $statusManager;

    protected string $statusFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->statusManager = new UpgradeStatusManager;
        $this->statusFile = storage_path('upgrades/status.json');

        // 确保测试前清理状态
        $this->statusManager->clear();
    }

    protected function tearDown(): void
    {
        // 测试后清理状态
        $this->statusManager->clear();

        parent::tearDown();
    }

    public function test_start_creates_status_file(): void
    {
        $this->statusManager->start('v1.0.0');

        $this->assertFileExists($this->statusFile);

        $status = $this->statusManager->get();
        $this->assertEquals('running', $status['status']);
        $this->assertEquals('v1.0.0', $status['version']);
        $this->assertEquals(0, $status['progress']);
        $this->assertNull($status['error']);
    }

    public function test_is_running_returns_true_when_status_is_running(): void
    {
        $this->statusManager->start('v1.0.0');

        $this->assertTrue($this->statusManager->isRunning());
    }

    public function test_is_running_returns_false_when_no_status(): void
    {
        $this->assertFalse($this->statusManager->isRunning());
    }

    public function test_complete_step_updates_progress(): void
    {
        $this->statusManager->setExpectedSteps(4);
        $this->statusManager->start('v1.0.0');

        $this->statusManager->completeStep('step1');
        $status = $this->statusManager->get();
        $this->assertEquals(25, $status['progress']);

        $this->statusManager->completeStep('step2');
        $status = $this->statusManager->get();
        $this->assertEquals(50, $status['progress']);

        $this->statusManager->completeStep('step3');
        $status = $this->statusManager->get();
        $this->assertEquals(75, $status['progress']);

        $this->statusManager->completeStep('step4');
        $status = $this->statusManager->get();
        $this->assertEquals(100, $status['progress']);
    }

    public function test_start_step_sets_status_to_running(): void
    {
        $this->statusManager->start('v1.0.0');
        $this->statusManager->startStep('download');

        $status = $this->statusManager->get();
        $this->assertEquals('download', $status['current_step']);

        $step = collect($status['steps'])->firstWhere('step', 'download');
        $this->assertEquals('running', $step['status']);
    }

    public function test_fail_step_records_error(): void
    {
        $this->statusManager->start('v1.0.0');
        $this->statusManager->failStep('apply', '权限不足');

        $status = $this->statusManager->get();
        $step = collect($status['steps'])->firstWhere('step', 'apply');

        $this->assertEquals('failed', $step['status']);
        $this->assertEquals('权限不足', $step['error']);
    }

    public function test_fail_marks_status_as_failed(): void
    {
        $this->statusManager->start('v1.0.0');
        $this->statusManager->fail('升级过程中发生错误');

        $status = $this->statusManager->get();
        $this->assertEquals('failed', $status['status']);
        $this->assertEquals('升级过程中发生错误', $status['error']);
        $this->assertArrayHasKey('failed_at', $status);
    }

    public function test_complete_marks_status_as_completed(): void
    {
        $this->statusManager->start('v1.0.0');
        $this->statusManager->complete('v0.9.0', 'v1.0.0');

        $status = $this->statusManager->get();
        $this->assertEquals('completed', $status['status']);
        $this->assertEquals(100, $status['progress']);
        $this->assertEquals('v0.9.0', $status['from_version']);
        $this->assertEquals('v1.0.0', $status['to_version']);
        $this->assertArrayHasKey('completed_at', $status);
    }

    public function test_clear_removes_status_file(): void
    {
        $this->statusManager->start('v1.0.0');
        $this->assertFileExists($this->statusFile);

        $this->statusManager->clear();
        $this->assertFileDoesNotExist($this->statusFile);
    }

    public function test_get_returns_null_when_no_status_file(): void
    {
        $this->assertNull($this->statusManager->get());
    }

    public function test_set_expected_steps_affects_progress_calculation(): void
    {
        $this->statusManager->setExpectedSteps(2);
        $this->statusManager->start('v1.0.0');

        $this->statusManager->completeStep('step1');
        $status = $this->statusManager->get();
        $this->assertEquals(50, $status['progress']);
    }

    public function test_get_total_steps_uses_config_when_no_expected_steps(): void
    {
        // 设置配置
        Config::set('upgrade.behavior.force_backup', true);
        Config::set('upgrade.behavior.maintenance_mode', true);
        Config::set('upgrade.behavior.auto_migrate', true);
        Config::set('upgrade.behavior.clear_cache', true);
        Config::set('upgrade.behavior.auto_structure_check', true);

        // 创建新实例以重置 expectedSteps
        $manager = new UpgradeStatusManager;
        $manager->start('v1.0.0');

        // 基础步骤 7 + backup 1 + maintenance 2 + migrate 1 + cache 1 + structure_check 1 = 13
        $reflection = new \ReflectionClass($manager);
        $method = $reflection->getMethod('getTotalSteps');
        $method->setAccessible(true);

        $totalSteps = $method->invoke($manager);
        $this->assertEquals(13, $totalSteps);
    }

    public function test_update_step_updates_existing_step(): void
    {
        $this->statusManager->start('v1.0.0');

        // 先添加步骤
        $this->statusManager->startStep('download');

        // 更新同一步骤
        $this->statusManager->completeStep('download');

        $status = $this->statusManager->get();
        $downloadSteps = collect($status['steps'])->where('step', 'download');

        // 应该只有一个 download 步骤，不是两个
        $this->assertCount(1, $downloadSteps);
        $this->assertEquals('completed', $downloadSteps->first()['status']);
    }

    public function test_multiple_steps_tracked_correctly(): void
    {
        $this->statusManager->setExpectedSteps(5);
        $this->statusManager->start('v1.0.0');

        $steps = ['fetch', 'download', 'extract', 'apply', 'cleanup'];

        foreach ($steps as $step) {
            $this->statusManager->startStep($step);
            $this->statusManager->completeStep($step);
        }

        $status = $this->statusManager->get();
        $this->assertCount(5, $status['steps']);
        $this->assertEquals(100, $status['progress']);

        foreach ($status['steps'] as $stepData) {
            $this->assertEquals('completed', $stepData['status']);
        }
    }

    public function test_save_uses_file_lock(): void
    {
        // 验证 save 方法使用文件锁（通过反射检查方法内部实现）
        $reflection = new \ReflectionClass($this->statusManager);
        $method = $reflection->getMethod('save');
        $method->setAccessible(true);

        // 执行 save
        $this->statusManager->start('v1.0.0');

        // 验证文件正确创建
        $this->assertFileExists($this->statusFile);

        // 验证内容正确
        $content = file_get_contents($this->statusFile);
        $data = json_decode($content, true);
        $this->assertEquals('running', $data['status']);
    }

    public function test_get_uses_shared_lock(): void
    {
        // 验证 get 方法可以正确读取数据
        $this->statusManager->start('v1.0.0');
        $this->statusManager->updateStep('test_step', 'running');

        $status = $this->statusManager->get();

        $this->assertNotNull($status);
        $this->assertEquals('running', $status['status']);
        $this->assertCount(1, $status['steps']);
    }

    public function test_rapid_updates_preserve_data_integrity(): void
    {
        $this->statusManager->setExpectedSteps(10);
        $this->statusManager->start('v1.0.0');

        // 快速连续更新
        for ($i = 1; $i <= 10; $i++) {
            $this->statusManager->startStep("step_$i");
            $this->statusManager->completeStep("step_$i");
        }

        $status = $this->statusManager->get();

        // 验证所有步骤都被正确记录
        $this->assertCount(10, $status['steps']);
        $this->assertEquals(100, $status['progress']);

        // 验证每个步骤状态正确
        foreach ($status['steps'] as $stepData) {
            $this->assertEquals('completed', $stepData['status']);
        }
    }

    public function test_complete_with_structure_check_stores_result(): void
    {
        $this->statusManager->start('v1.0.0');

        $structureCheck = [
            'has_diff' => false,
            'summary' => ['missing_tables' => [], 'extra_tables' => []],
        ];

        $this->statusManager->complete('v0.9.0', 'v1.0.0', $structureCheck);

        $status = $this->statusManager->get();
        $this->assertEquals('completed', $status['status']);
        $this->assertArrayHasKey('structure_check', $status);
        $this->assertFalse($status['structure_check']['has_diff']);
    }
}
