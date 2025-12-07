<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * 定义应用程序的命令调度
     * 注意：Laravel 11 中调度配置已迁移到 routes/console.php
     */
    protected function schedule(Schedule $schedule): void
    {
        // 在 Laravel 11 中，调度配置应该在 routes/console.php 中定义
        // 这个方法保留用于兼容性
    }

    /**
     * 注册应用程序的命令
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }
}
