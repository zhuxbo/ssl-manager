<?php

/**
 * 安装模块自动加载器
 * PSR-4 风格的类自动加载
 */

spl_autoload_register(function ($class) {
    $prefix = 'Install\\';
    $baseDir = __DIR__ . '/';

    // 检查类名是否使用指定前缀
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    // 获取相对类名
    $relativeClass = substr($class, strlen($prefix));

    // 将命名空间分隔符替换为目录分隔符
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
