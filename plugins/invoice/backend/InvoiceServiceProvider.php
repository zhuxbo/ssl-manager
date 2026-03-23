<?php

namespace Plugins\Invoice;

use Illuminate\Support\ServiceProvider;

class InvoiceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $basePath = dirname(__DIR__);

        $this->loadRoutesFrom("$basePath/backend/routes/admin.php");
        $this->loadRoutesFrom("$basePath/backend/routes/user.php");
        $this->loadMigrationsFrom("$basePath/backend/migrations");
    }
}
