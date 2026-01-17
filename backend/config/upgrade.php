<?php

/**
 * 升级配置
 */

return [
    // 备份配置
    'backup' => [
        // 备份存储目录（项目根目录，与 upgrade.sh 一致）
        'path' => dirname(base_path()) . '/storage/backups',

        // 最大保留备份数量
        'max_backups' => env('UPGRADE_MAX_BACKUPS', 5),

        // 备份内容
        'include' => [
            'backend' => true,      // 后端代码
            'frontend' => true,     // 前端（编译后的 dist 目录）
            'database' => false,    // 数据库（Docker 环境无 mysqldump）
        ],

        // 数据库备份排除的表（日志表、队列表、通知记录等非核心数据）
        'exclude_tables' => [
            // 日志表
            'ca_logs',
            'callback_logs',
            'api_logs',
            'user_logs',
            'admin_logs',
            'error_logs',
            'easy_logs',
            // 队列表
            'jobs',
            'failed_jobs',
            'job_batches',
            // 通知记录
            'notifications',
        ],
    ],

    // 升级包配置
    'package' => [
        // 下载目录
        'download_path' => storage_path('upgrades'),

        // 下载超时（秒）
        'download_timeout' => 300,

        // 自动清理旧的升级包
        'auto_cleanup' => true,

        // 升级包保留天数
        'retention_days' => 30,
    ],

    // 升级行为配置
    'behavior' => [
        // 升级前是否强制备份
        'force_backup' => true,

        // 升级前是否进入维护模式
        'maintenance_mode' => true,

        // 升级后是否自动清理缓存
        'clear_cache' => true,

        // 升级后是否自动运行迁移
        'auto_migrate' => true,

        // 升级后是否自动运行种子（seeder 有防御性检测，可重复执行）
        'auto_seed' => true,

        // 指定种子类，null 表示 DatabaseSeeder
        'seed_class' => null,
    ],

    // 版本限制
    'constraints' => [
        // 最低支持的 PHP 版本
        'min_php_version' => '8.3.0',

        // 不允许降级
        'allow_downgrade' => false,

        // 允许跨版本升级（全量替换方式）
        'require_sequential' => false,
    ],
];
