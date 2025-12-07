<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Symfony\Component\Process\Process;

class ScheduleRunForegroundCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'schedule:run-fg {--show-output : 显示命令输出}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '在前台运行调度任务并显示所有输出';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->newLine();
        $this->line('----------------------------------------');
        $this->info('SSL证书管理系统 - 前台调度任务执行器');
        $this->line('----------------------------------------');
        $this->info('执行时间: '.now()->format('Y-m-d H:i:s'));
        $this->newLine();

        /** @var Schedule $schedule */
        $schedule = app(Schedule::class);
        $events = $schedule->dueEvents(app());

        if (count($events) === 0) {
            $this->info('没有需要执行的调度任务');
            $this->displayAllEvents($schedule);

            return;
        }

        $this->info('找到 '.count($events).' 个需要执行的任务:');
        $this->newLine();

        $executedCount = 0;
        $failedCount = 0;

        foreach ($events as $event) {
            $this->displayEventInfo($event);

            $startTime = microtime(true);
            $this->line('开始执行...');

            try {
                // 直接运行 Artisan 命令并捕获输出
                $commandParts = $event->buildCommand();

                // 提取实际的 artisan 命令
                if (preg_match("/'artisan'\s+(.+?)(?:\s|$)/", $commandParts, $matches)) {
                    $artisanCommand = trim($matches[1]);

                    $this->newLine();
                    $this->line('执行命令输出:');
                    $this->line('----------------------------------------');

                    // 直接调用 Artisan 命令并显示输出
                    $exitCode = $this->call($artisanCommand);

                    $this->line('----------------------------------------');

                    $endTime = microtime(true);
                    $duration = round(($endTime - $startTime) * 1000, 2);
                    if ($exitCode === 0) {
                        $this->line("执行成功 (耗时: {$duration}ms)");
                        $executedCount++;
                    } else {
                        $this->error("✗ 执行失败 (耗时: {$duration}ms, 退出码: $exitCode)");
                        $failedCount++;
                    }
                } else {
                    // 如果不是 artisan 命令，使用系统命令执行
                    $process = Process::fromShellCommandline($commandParts);
                    $process->setTimeout(300); // 5分钟超时

                    $this->newLine();
                    $this->line('执行系统命令输出:');
                    $this->line('----------------------------------------');

                    $process->run(function ($type, $buffer) {
                        echo $buffer;
                    });

                    $this->line('----------------------------------------');

                    $endTime = microtime(true);
                    $duration = round(($endTime - $startTime) * 1000, 2);
                    if ($process->isSuccessful()) {
                        $this->line("执行成功 (耗时: {$duration}ms)");
                        $executedCount++;
                    } else {
                        $this->error("执行失败 (耗时: {$duration}ms)");
                        $this->error('错误信息: '.$process->getErrorOutput());
                        $failedCount++;
                    }
                }

            } catch (Exception $e) {
                $endTime = microtime(true);
                $duration = round(($endTime - $startTime) * 1000, 2);

                $this->error("执行失败 (耗时: {$duration}ms)");
                $this->error('错误信息: '.$e->getMessage());
                $failedCount++;
            }

            $this->newLine();
        }

        $this->info('执行结果汇总:');
        $this->line('• 总任务数: '.count($events));
        $this->line("• 成功执行: $executedCount");
        $this->line("• 执行失败: $failedCount");

        if ($failedCount > 0) {
            $this->warn('存在执行失败的任务，请检查上述错误信息');
        }
    }

    /**
     * 显示事件信息
     */
    private function displayEventInfo($event): void
    {
        $command = $event->command ?? 'Unknown';
        $description = $event->description ?? 'No description';

        $this->line("任务: $command");
        $this->line("描述: $description");
        $this->line('调度时间: '.$this->getScheduleDescription($event));
    }

    /**
     * 显示所有调度事件（当没有需要执行的任务时）
     */
    private function displayAllEvents(Schedule $schedule): void
    {
        $this->newLine();
        $this->info('所有已配置的调度任务:');
        $this->info('----------------------------------------');

        $allEvents = $schedule->events();

        foreach ($allEvents as $event) {
            $command = $event->command ?? 'Unknown';
            $description = $event->description ?? 'No description';
            $nextRun = $event->nextRunDate();
            $nextRunStr = $nextRun->format('Y-m-d H:i:s');

            $this->line("任务: $command");
            $this->line("描述: $description");
            $this->line("下次运行: $nextRunStr");
            $this->line('是否应该运行: '.($event->isDue(app()) ? '是' : '否'));
            $this->newLine();
        }
    }

    /**
     * 获取调度描述
     */
    private function getScheduleDescription($event): string
    {
        $expression = $event->expression;

        $descriptions = [
            '* * * * *' => '每分钟',
            '0 * * * *' => '每小时',
            '0 0 * * *' => '每天午夜',
            '0 9 * * *' => '每天上午9点',
            '0 2 * * *' => '每天凌晨2点',
            '0 0 * * 0' => '每周日午夜',
            '0 0 1 * *' => '每月1号午夜',
        ];

        return $descriptions[$expression] ?? $expression;
    }
}
