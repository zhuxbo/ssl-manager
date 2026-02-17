<?php

namespace App\Providers;

use App\Models\Cert;
use App\Observers\CertObserver;
use App\Services\LogBuffer;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 加载所有辅助函数文件
        $helperPath = app_path('Helpers');
        foreach (glob($helperPath.'/*.php') as $file) {
            require_once $file;
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Cert::observe(CertObserver::class);

        // 队列任务执行完毕后刷新日志缓冲区
        Queue::after(function (JobProcessed $event) {
            LogBuffer::flush();
        });

        Queue::failing(function (JobFailed $event) {
            LogBuffer::flush();
        });

        // 异常后 release 重试场景（不触发 after/failing）
        Queue::exceptionOccurred(function (JobExceptionOccurred $event) {
            LogBuffer::flush();
        });

        // CLI (Artisan) 场景兜底刷新
        $this->app->terminating(function () {
            LogBuffer::flush();
        });
    }
}
