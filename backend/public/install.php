<?php

/**
 * SSL 证书管理系统 - 安装向导入口
 *
 * 检测 PHP 环境是否符合要求，配置系统并运行必需的命令
 */

// 设置脚本超时时间
set_time_limit(300);

// 错误处理
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 将配置信息存储到会话中，防止在表单间丢失
session_start();

// 尝试禁用 zlib 压缩，因为它可能干扰 flush
@ini_set('zlib.output_compression', 'Off');
// 强制关闭输出缓冲
@ob_end_clean();
@ini_set('output_buffering', 'Off');
// 尝试启用隐式刷新
@ini_set('implicit_flush', 1);
@ob_implicit_flush();

// 加载自动加载器
require_once __DIR__ . '/install-assets/autoload.php';

// 创建控制器并处理请求
$controller = new Install\InstallController();
$controller->handleRequest();
