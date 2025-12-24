<?php

/**
 * 版本配置
 *
 * 此文件在构建时会被更新
 */

return [
    // 当前版本号（语义化版本）
    'version' => env('APP_VERSION', '1.0.0'),

    // 版本名称
    'name' => 'SSL Manager',

    // 构建时间
    'build_time' => env('APP_BUILD_TIME', ''),

    // 构建 commit
    'build_commit' => env('APP_BUILD_COMMIT', ''),

    // 发布通道：main（正式版）或 dev（开发版）
    'channel' => env('APP_RELEASE_CHANNEL', 'main'),
];
