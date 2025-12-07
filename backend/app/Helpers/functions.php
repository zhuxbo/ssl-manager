<?php

declare(strict_types=1);

use App\Models\Setting;

if (! function_exists('get_system_setting')) {
    /**
     * 获取系统配置
     */
    function get_system_setting(string $group, ?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return Setting::getByGroupName($group);
        }

        return Setting::getValue($group, $key) ?? $default;
    }
}

if (! function_exists('getModule')) {
    /**
     * 获取模块名称
     *
     * @param  string  $name  完整名称
     * @return string 模块名称
     */
    function getModule(string $name): string
    {
        if (str_contains($name, '/')) {
            return substr($name, 0, strpos($name, '/'));
        }

        return $name;
    }
}

if (! function_exists('getAction')) {
    /**
     * 获取动作名称
     *
     * @param  string  $name  完整名称
     * @return string 动作名称
     */
    function getAction(string $name): string
    {
        if (str_contains($name, '/')) {
            return substr($name, strpos($name, '/') + 1);
        }

        return 'index';
    }
}

if (! function_exists('getCurrentModule')) {
    /**
     * 获取当前模块名称
     *
     * @return string 当前模块名称
     */
    function getCurrentModule(): string
    {
        // 检查是否在命令行环境中
        if (app()->runningInConsole()) {
            // 获取当前命令名称
            $command = null;
            if (isset($_SERVER['argv'][1])) {
                $command = $_SERVER['argv'][1];
            }

            // 如果有命令，返回命令名，否则返回console'
            return $command ?: 'console';
        }

        // Web环境下，从路由获取
        $routeName = request()->route() ? request()->route()->getName() : '';

        if ($routeName) {
            return getModule($routeName);
        }

        // 如果没有路由名称，尝试从控制器获取
        $action = request()->route() ? request()->route()->getActionName() : '';

        if ($action && str_contains($action, '@')) {
            $controller = substr($action, 0, strpos($action, '@'));
            $controllerParts = explode('\\', $controller);
            $controllerName = end($controllerParts);

            // 移除Controller后缀
            return str_replace('Controller', '', $controllerName);
        }

        return 'unknown';
    }
}

if (! function_exists('getCurrentAction')) {
    /**
     * 获取当前动作名称
     *
     * @return string 当前动作名称
     */
    function getCurrentAction(): string
    {
        // 检查是否在命令行环境中
        if (app()->runningInConsole()) {
            // 获取当前命令的参数
            $option = null;
            if (isset($_SERVER['argv'][2])) {
                $option = $_SERVER['argv'][2];
            }

            // 如果有参数，返回参数，否则返回run'
            return $option ?: 'run';
        }

        // Web环境下，从路由获取
        $routeName = request()->route() ? request()->route()->getName() : '';

        if ($routeName) {
            return getAction($routeName);
        }

        // 如果没有路由名称，尝试从控制器获取
        $action = request()->route() ? request()->route()->getActionName() : '';

        if ($action && str_contains($action, '@')) {
            return substr($action, strpos($action, '@') + 1);
        }

        return 'unknown';
    }
}

if (! function_exists('getControllerCategory')) {
    /**
     * 获取控制器来源分类
     *
     * @return string 控制器分类（如Admin、Api等）
     */
    function getControllerCategory(): string
    {
        // 检查是否在命令行环境中
        if (app()->runningInConsole()) {
            // 命令行环境中，尝试从命令类名解析分类
            $command = null;
            if (isset($_SERVER['argv'][1])) {
                $command = $_SERVER['argv'][1];
            }

            // 解析命令类型
            if ($command && str_contains($command, ':')) {
                return ucfirst(explode(':', $command)[0]);
            }

            return 'Console';
        }

        // Web环境下，从控制器命名空间解析分类
        $action = request()->route() ? request()->route()->getActionName() : '';

        if ($action) {
            // 解析控制器完整类名
            $controllerClass = substr($action, 0, strpos($action, '@') ?: strlen($action));

            // 解析命名空间部分
            $namespaceParts = explode('\\', $controllerClass);

            // 查找分类部分（如App\Http\Controllers\Admin\OrderController中的Admin）
            foreach ($namespaceParts as $index => $part) {
                if ($part === 'Controllers' && isset($namespaceParts[$index + 1])) {
                    return $namespaceParts[$index + 1];
                }
            }
        }

        return 'Unknown';
    }
}

if (! function_exists('is_json')) {

    /**
     * 判断是否为json
     */
    function is_json(mixed $json): bool
    {
        // json_decode() 在处理非UTF-8字符串时可能会失败，所以检查编码
        if (! is_string($json) || ! mb_check_encoding($json, 'UTF-8')) {
            return false;
        }

        $result = json_decode($json);

        return $result !== null || strtolower($json) === 'null';
    }
}

if (! function_exists('merge_multi_dimensional_arrays')) {

    /**
     * 合并多维数组
     */
    function merge_multi_dimensional_arrays(array $array1, array $array2): array
    {
        foreach ($array2 as $key => $value) {
            // 如果键存在且值是数组，递归合并
            if (isset($array1[$key]) && is_array($array1[$key]) && is_array($value)) {
                $array1[$key] = merge_multi_dimensional_arrays($array1[$key], $value);
            } else {
                // 否则直接替换
                $array1[$key] = $value;
            }
        }

        return $array1;
    }
}
