<?php

namespace App\Traits;

trait ResolvesExecutablePath
{
    /**
     * 解析可执行文件路径
     *
     * 使用 exec + command -v，避免依赖 shell_exec。
     */
    protected function resolveExecutablePath(string $command): ?string
    {
        if (! function_exists('exec')) {
            return null;
        }

        $output = [];
        $exitCode = 1;
        @exec('command -v '.escapeshellarg($command).' 2>/dev/null', $output, $exitCode);

        if ($exitCode !== 0 || empty($output[0])) {
            return null;
        }

        $path = trim((string) $output[0]);

        return $path !== '' ? $path : null;
    }
}
