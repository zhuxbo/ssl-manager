<?php

/**
 * 版本配置
 *
 * 优先从项目根目录的 config.json 读取版本信息
 * config.json 在构建发布包时自动生成
 */

// 尝试从 config.json 读取版本信息
$configJson = [];
$configPath = dirname(__DIR__, 2) . '/config.json';
if (file_exists($configPath)) {
    $content = file_get_contents($configPath);
    $configJson = json_decode($content, true) ?: [];
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
