<?php

namespace Install\Installer;

use Install\DTO\InstallConfig;
use Install\View\ProgressReporter;

/**
 * 安装执行器（协调器）
 */
class InstallExecutor
{
    private string $projectRoot;

    private EnvConfigurator $envConfigurator;

    private ComposerRunner $composerRunner;

    private KeyGenerator $keyGenerator;

    private DatabaseMigrator $databaseMigrator;

    private Cleaner $cleaner;

    private ProgressReporter $reporter;

    private array $steps = [];

    private bool $skipComposer = false;

    public function __construct(?string $projectRoot = null, ?ProgressReporter $reporter = null)
    {
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 3);
        $this->envConfigurator = new EnvConfigurator($this->projectRoot);
        $this->composerRunner = new ComposerRunner($this->projectRoot);
        $this->keyGenerator = new KeyGenerator($this->projectRoot);
        $this->databaseMigrator = new DatabaseMigrator($this->projectRoot);
        $publicDir = dirname(__DIR__, 2);
        $this->cleaner = new Cleaner($publicDir, $this->projectRoot);

        // 检测是否跳过 Composer 安装
        $this->skipComposer = $this->isComposerInstalled();
        $totalSteps = $this->skipComposer ? 7 : 8;
        $this->reporter = $reporter ?? new ProgressReporter($totalSteps);
    }

    /**
     * 执行完整的安装流程
     */
    public function execute(InstallConfig $config): array
    {
        $this->steps = [];
        $step = 0;

        try {
            chdir($this->projectRoot);

            // 步骤: 配置环境变量
            $this->reporter->startStep(++$step, '配置环境变量');
            if (! $this->envConfigurator->configure($config)) {
                throw new \Exception('环境变量配置失败');
            }
            $this->reporter->completeStep('环境变量配置完成');
            $this->steps[] = '配置环境变量';

            // 步骤: Composer 安装（如果已安装则跳过）
            if (! $this->skipComposer) {
                $this->reporter->startStep(++$step, '安装 Composer 依赖');
                $this->reporter->showOutput('正在安装依赖，可能需要较长时间...');
                if (! $this->composerRunner->install()) {
                    throw new \Exception('Composer 依赖安装失败 (返回码 ' . $this->composerRunner->getReturnCode() . ')');
                }
                $this->reporter->showOutput($this->composerRunner->getOutputString());
                $this->reporter->completeStep('Composer 依赖安装完成');
                $this->steps[] = '安装 Composer 依赖';
            }

            // 步骤: 生成应用密钥
            $this->reporter->startStep(++$step, '生成应用密钥');
            if (! $this->keyGenerator->generateAppKey()) {
                throw new \Exception('应用密钥生成失败');
            }
            $this->reporter->completeStep('应用密钥生成完成');
            $this->steps[] = '生成应用密钥';

            // 步骤: 生成 JWT 密钥
            $this->reporter->startStep(++$step, '生成 JWT 密钥');
            if (! $this->keyGenerator->generateJwtSecret()) {
                throw new \Exception('JWT 密钥生成失败');
            }
            $this->reporter->completeStep('JWT 密钥生成完成');
            $this->steps[] = '生成 JWT 密钥';

            // 步骤: 数据库迁移
            $this->reporter->startStep(++$step, '执行数据库迁移');
            if (! $this->databaseMigrator->migrate()) {
                throw new \Exception('数据库迁移失败 (返回码 ' . $this->databaseMigrator->getReturnCode() . ')');
            }
            $this->reporter->showOutput($this->databaseMigrator->getOutputString());
            $this->reporter->completeStep('数据库迁移命令执行成功');
            $this->steps[] = '执行数据库迁移';

            // 步骤: 数据填充
            $this->reporter->startStep(++$step, '执行数据填充');
            if ($this->databaseMigrator->seed()) {
                $this->reporter->completeStep('数据填充命令执行成功');
                $this->steps[] = '执行数据填充';
            } else {
                $this->reporter->showWarning('数据填充失败，但不影响安装');
            }

            // 步骤: 优化配置
            $this->reporter->startStep(++$step, '优化应用配置和路由');
            if ($this->databaseMigrator->optimize()) {
                $this->reporter->completeStep('配置和路由缓存完成');
                $this->steps[] = '优化应用配置和路由';
            } else {
                $this->reporter->showWarning('优化配置失败，但不影响安装');
            }

            // 步骤: 清理安装文件
            $this->reporter->startStep(++$step, '清理安装文件');
            if ($this->cleaner->scheduleCleanup()) {
                $this->reporter->completeStep('安装文件清理已安排');
                $this->steps[] = '清理安装文件';
            } else {
                $this->reporter->showWarning('无法自动清理安装文件，请手动删除');
            }

            return [
                'success' => true,
                'steps' => $this->steps,
            ];
        } catch (\Exception $e) {
            $this->reporter->showError($e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'steps' => $this->steps,
            ];
        }
    }

    /**
     * 检测 Composer 依赖是否已安装
     */
    private function isComposerInstalled(): bool
    {
        $autoloadPath = $this->projectRoot . '/vendor/autoload.php';

        return file_exists($autoloadPath);
    }

    /**
     * 获取已完成的步骤
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    /**
     * 获取进度报告器
     */
    public function getReporter(): ProgressReporter
    {
        return $this->reporter;
    }
}
