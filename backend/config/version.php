<?php

/**
 * 版本配置
 *
 * 优先从项目根目录的 config.json 读取版本信息
 * config.json 在构建发布包时自动生成
 */

// 尝试从 config.json 读取版本信息（支持两个位置）
$configJson = [];
$configPaths = [
    dirname(__DIR__, 2) . '/config.json',  // 项目根目录
    dirname(__DIR__) . '/config.json',      // backend 目录（Docker 环境）
];

foreach ($configPaths as $configPath) {
    if (file_exists($configPath)) {
        $content = file_get_contents($configPath);
        $configJson = json_decode($content, true) ?: [];
        if (! empty($configJson)) {
            break;
        }
    }
}

return [
    // 当前版本号（语义化版本）
    'version' => $configJson['version'] ?? env('APP_VERSION', '0.0.0'),

    // 版本名称
    'name' => 'SSL Manager',

    // 构建时间
    'build_time' => $configJson['build_time'] ?? env('APP_BUILD_TIME', ''),

    // 构建 commit
    'build_commit' => env('APP_BUILD_COMMIT', ''),

    // 发布通道：main（正式版）或 dev（开发版）
    'channel' => $configJson['channel'] ?? env('APP_RELEASE_CHANNEL', 'main'),
];
