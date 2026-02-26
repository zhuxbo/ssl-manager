<?php

namespace Plugins\Easy;

use App\Contracts\PluginLogHandler;
use App\Services\LogBuffer;
use Plugins\Easy\Models\EasyLog;

class EasyLogHandler implements PluginLogHandler
{
    public function shouldHandle(string $path): bool
    {
        return str_starts_with($path, 'api/easy/');
    }

    public function handle(array $logData): void
    {
        LogBuffer::add(EasyLog::class, $logData);
    }
}
