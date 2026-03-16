<?php

namespace Plugins\Notice;

use Illuminate\Support\ServiceProvider;

class NoticeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $basePath = dirname(__DIR__);

        $this->loadRoutesFrom("$basePath/backend/routes/admin.php");
        $this->loadRoutesFrom("$basePath/backend/routes/user.php");
        $this->loadMigrationsFrom("$basePath/backend/migrations");
    }
}
