<?php

/** @noinspection PhpMultipleClassDeclarationsInspection */
/** @noinspection JSUnresolvedReference */
/** @noinspection DuplicatedCode */

/**
 * SSL 后端自动安装脚本（使用模板系统）
 * 检测PHP环境是否符合要求，并运行必需的命令
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

// 加载模板助手
require_once __DIR__.'/install-assets/template-helper.php';

// 初始化变量
$canProceed = true;
$errors = [];
$warnings = [];
$steps = [];

// 检查安装状态
$envExists = file_exists(dirname(__DIR__).'/.env');
$isInstalled = false;

if ($envExists) {
    // 如果.env文件存在，进一步检查是否已完成安装
    // 检查数据库是否已初始化（查看是否有用户表）
    try {
        // 尝试读取.env文件获取数据库配置
        $envContent = file_get_contents(dirname(__DIR__).'/.env');

        // 初始化变量
        $dbHost = null;
        $dbPort = null;
        $dbDatabase = null;
        $dbUsername = null;
        $dbPassword = '';

        // 解析.env文件内容
        if (preg_match('/DB_HOST=(.+)/', $envContent, $matches)) {
            $dbHost = trim($matches[1]);
        }
        if (preg_match('/DB_PORT=(.+)/', $envContent, $matches)) {
            $dbPort = trim($matches[1]);
        }
        if (preg_match('/DB_DATABASE=(.+)/', $envContent, $matches)) {
            $dbDatabase = trim($matches[1]);
        }
        if (preg_match('/DB_USERNAME=(.+)/', $envContent, $matches)) {
            $dbUsername = trim($matches[1]);
        }
        if (preg_match('/DB_PASSWORD=(.+)/', $envContent, $matches)) {
            $dbPassword = trim($matches[1]);
        }

        // 检查是否获取到必要的数据库配置
        if (! empty($dbHost) && ! empty($dbPort) && ! empty($dbDatabase) && ! empty($dbUsername)) {
            try {
                $dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbDatabase";
                $pdo = new PDO($dsn, $dbUsername, $dbPassword, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 3,
                ]);

                // 检查是否存在管理员表和用户表
                $adminsTable = $pdo->query("SHOW TABLES LIKE 'admins'");
                $usersTable = $pdo->query("SHOW TABLES LIKE 'users'");

                if ($adminsTable && $adminsTable->rowCount() > 0) {
                    // 检查管理员表是否有数据
                    /** @noinspection SqlResolve */
                    $adminCount = $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
                    if ($adminCount > 0) {
                        $isInstalled = true;
                    }
                } elseif ($usersTable && $usersTable->rowCount() > 0) {
                    // 如果没有管理员表，检查用户表
                    /** @noinspection SqlResolve */
                    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users');
                    $stmt->execute();
                    $userCount = $stmt->fetchColumn();
                    if ($userCount > 0) {
                        $isInstalled = true;
                    }
                }
            } catch (PDOException $e) {
                // 数据库连接失败，可能是配置问题
                $isInstalled = false;
            }
        } else {
            // 数据库配置不完整
            $isInstalled = false;
        }
    } catch (Exception $e) {
        // 读取.env文件失败
        $isInstalled = false;
    }

    if (! $isInstalled) {
        $canProceed = false;
        $errors[] = '检测到已存在的 .env 文件，但系统未完成安装。为避免覆盖现有配置，安装程序已停止。如需重新安装，请先删除根目录中的 .env 文件。';
    }
}

// 存储配置信息
$config = [
    'db_host' => '127.0.0.1',
    'db_port' => '3306',
    'db_database' => '',
    'db_username' => '',
    'db_password' => '',
    'redis_host' => '127.0.0.1',
    'redis_port' => '6379',
    'redis_username' => '',
    'redis_password' => '',
];

// 从会话中恢复配置（如果存在）
if (isset($_SESSION['install_config']) && is_array($_SESSION['install_config'])) {
    $config = array_merge($config, $_SESSION['install_config']);
}

// 从会话中恢复数据库连接状态（如果存在）
$dbConnected = $_SESSION['install_db_connected'] ?? false;
$dbEmpty = $_SESSION['install_db_empty'] ?? false;
$preInstallErrors = $_SESSION['install_pre_install_errors'] ?? [];

// 处理表单提交
$formStage = 'env';
$showConfigForm = true;

if (isset($_POST['action'])) {
    if ($_POST['action'] == 'config') {
        // 收集配置信息
        foreach ($config as $key => $value) {
            if (isset($_POST[$key])) {
                $config[$key] = trim($_POST[$key]);
            }
        }

        // 保存到会话
        $_SESSION['install_config'] = $config;
        $_SESSION['install_pre_install_errors'] = [];

        // 确保数据库信息已设置
        if (empty($config['db_host']) || empty($config['db_database']) || empty($config['db_username'])) {
            $errors[] = '数据库配置不完整，请填写必要的数据库信息';
        } else {
            // 测试数据库连接
            try {
                $dsn = "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_database']}";
                $pdo = new PDO($dsn, $config['db_username'], $config['db_password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 5,
                ]);

                $dbConnected = true;

                // 获取 MySQL 版本
                $versionResult = $pdo->query('SELECT VERSION()')->fetch();
                $mysqlVersion = $versionResult[0];
                // 提取主版本号
                preg_match('/(\d+\.\d+)/', $mysqlVersion, $versionMatch);
                $majorVersion = floatval($versionMatch[1] ?? '5.7');

                // 根据版本设置适合的排序规则
                if ($majorVersion >= 8.0) {
                    $config['db_collation'] = 'utf8mb4_0900_ai_ci';
                } else {
                    $config['db_collation'] = 'utf8mb4_unicode_520_ci';
                }

                // 保存版本信息到会话
                $config['mysql_version'] = $mysqlVersion;
                $config['mysql_major_version'] = $majorVersion;

                // 更新会话中的配置
                $_SESSION['install_config'] = $config;

                // 检查数据库是否为空
                $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
                $dbEmpty = count($tables) === 0;

                if (! $dbEmpty) {
                    $tablesList = implode(', ', $tables);
                    $errors[] = '数据库不为空，包含'.count($tables).'个表 ('.(strlen($tablesList) > 100 ? substr($tablesList, 0,
                        100).'...' : $tablesList).')，必须使用空数据库进行安装';
                    $canProceed = false;
                }
                // 注意：不要在这里设置 $formStage = 'install'，需要等 Redis 检测也成功后才能设置

                $_SESSION['install_db_connected'] = $dbConnected;
                $_SESSION['install_db_empty'] = $dbEmpty;
            } catch (PDOException $e) {
                $errors[] = '无法连接到数据库: '.$e->getMessage();
                $_SESSION['install_db_connected'] = false;
                $_SESSION['install_db_empty'] = false;
                $canProceed = false;

                // 尝试测试服务器是否可访问
                try {
                    $socket = @fsockopen($config['db_host'], $config['db_port'], $errNo, $errStr, 3);
                    if (! $socket) {
                        $errors[] = '无法连接到数据库服务器，网络可能不通: '.$errNo.' - '.$errStr;
                    } else {
                        fclose($socket);
                        $warnings[] = '数据库服务器可以访问，但连接被拒绝，请检查用户名/密码或数据库名';
                    }
                } catch (Exception $e2) {
                    $errors[] = '网络测试失败: '.$e2->getMessage();
                }
            }

            // 测试Redis连接（必选项）
            if (! extension_loaded('redis')) {
                $errors[] = 'Redis扩展未加载，请先安装并启用Redis扩展';
                $canProceed = false;
                $formStage = 'env';
            } else {
                $redisConnected = false;
                $redisError = '';

                try {
                    $redis = new Redis;

                    // 尝试连接Redis
                    if (! $redis->connect($config['redis_host'], $config['redis_port'], 2)) {
                        $redisError = '无法连接到Redis服务器，请检查Redis主机和端口配置';
                    } else {
                        // 如果用户配置了密码，先进行认证
                        if (! empty($config['redis_password'])) {
                            try {
                                // Redis 6.0+ 支持用户名和密码
                                if (! empty($config['redis_username'])) {
                                    $authResult = $redis->auth([$config['redis_username'], $config['redis_password']]);
                                } else {
                                    // 旧版Redis只使用密码
                                    $authResult = $redis->auth($config['redis_password']);
                                }

                                if (! $authResult) {
                                    $redisError = 'Redis认证失败，用户名或密码错误（请确认是否配置用户名）';
                                } else {
                                    // 认证成功后测试ping
                                    try {
                                        $pingResult = $redis->ping();
                                        if ($pingResult === true || $pingResult === '+PONG' || $pingResult === 'PONG') {
                                            $redisConnected = true;
                                        } else {
                                            $redisError = 'Redis认证成功但PING测试失败';
                                        }
                                    } catch (Exception $e2) {
                                        $redisError = 'Redis认证成功但无法执行PING命令: '.$e2->getMessage();
                                    }
                                }
                            } catch (Exception $e) {
                                // 检查是否是认证相关的错误
                                $errorMsg = $e->getMessage();
                                if (stripos($errorMsg, 'WRONGPASS') !== false ||
                                    stripos($errorMsg, 'invalid password') !== false ||
                                    stripos($errorMsg, 'invalid username-password') !== false ||
                                    stripos($errorMsg, 'ERR invalid password') !== false) {
                                    $redisError = 'Redis认证失败，用户名或密码错误（请确认是否配置用户名）';
                                } else {
                                    $redisError = 'Redis认证过程出错: '.$errorMsg;
                                }
                            }
                        } else {
                            // 没有配置密码，直接尝试ping
                            try {
                                $pingResult = $redis->ping();
                                if ($pingResult === true || $pingResult === '+PONG' || $pingResult === 'PONG') {
                                    $redisConnected = true;
                                } else {
                                    $redisError = 'Redis PING测试返回异常结果';
                                }
                            } catch (Exception $e) {
                                // ping失败，可能需要认证
                                $errorMsg = $e->getMessage();
                                if (stripos($errorMsg, 'NOAUTH') !== false ||
                                    stripos($errorMsg, 'Authentication required') !== false) {
                                    $redisError = 'Redis服务器需要密码认证，请填写Redis密码';
                                } else {
                                    $redisError = 'Redis PING测试失败: '.$errorMsg;
                                }
                            }
                        }

                        $redis->close();
                    }
                } catch (Exception $e) {
                    $redisError = 'Redis连接测试失败: '.$e->getMessage();
                }

                // 如果Redis连接失败，添加错误并阻止继续
                if (! $redisConnected) {
                    $errors[] = $redisError ?: 'Redis连接失败，原因未知';
                    $canProceed = false;
                    // 重置表单阶段，强制用户修正配置
                    $formStage = 'env';
                } else {
                    // Redis 连接成功，且之前没有设置错误，可以进入安装阶段
                    if ($dbConnected && $dbEmpty && $canProceed) {
                        $formStage = 'install';
                    }
                }
            }
        }
    } elseif ($_POST['action'] == 'install') {
        $formStage = 'install';
        $showConfigForm = false;

        // 恢复配置信息
        foreach ($config as $key => $value) {
            if (isset($_POST[$key])) {
                $config[$key] = trim($_POST[$key]);
            }
        }

        $_SESSION['install_config'] = $config;
        $_SESSION['install_pre_install_errors'] = [];
    }
}

// ===========================================
// 系统环境检查
// ===========================================

// 检查PHP版本
$phpVersion = phpversion();
$requiredPhpVersion = '8.3.0';
$phpVersionValid = version_compare($phpVersion, $requiredPhpVersion, '>=');

if (! $phpVersionValid) {
    $canProceed = false;
    $errors[] = "PHP版本必须 >= {$requiredPhpVersion}，当前版本: $phpVersion";
}

// 检查必要的PHP扩展
$requiredExtensions = [
    'bcmath', 'calendar', 'fileinfo', 'gd', 'iconv', 'intl', 'json',
    'openssl', 'pcntl', 'pdo', 'pdo_mysql', 'redis', 'zip', 'mbstring', 'curl',
];

$extensionRequirements = [];
$extensionSuccess = true;

// 检查exec函数是否可用（需要在宝塔环境检测之前定义）
$execEnabled = function_exists('exec') && ! in_array('exec', array_map('trim', explode(',', ini_get('disable_functions'))));

// 检测宝塔环境并设置正确的PHP路径
$phpBinary = 'php';
$isBaotaEnv = false;

// 安全检测宝塔环境，避免 open_basedir 限制
// 方法1: 通过当前脚本路径特征判断
if (str_starts_with(__DIR__, '/www/wwwroot/')) {
    $isBaotaEnv = true;
    $phpBinary = '/www/server/php/83/bin/php';
} // 方法2: 通过服务器环境变量判断
elseif (isset($_SERVER['DOCUMENT_ROOT']) && str_contains($_SERVER['DOCUMENT_ROOT'], '/www/wwwroot/')) {
    $isBaotaEnv = true;
    $phpBinary = '/www/server/php/83/bin/php';
} // 方法3: 通过PHP配置路径判断
elseif (str_contains(ini_get('include_path'), '/www/server/')) {
    $isBaotaEnv = true;
    $phpBinary = '/www/server/php/83/bin/php';
} // 方法4: 如果exec可用，通过命令行检测
elseif ($execEnabled) {
    exec('which php 2>/dev/null', $phpPathOutput, $phpPathReturn);
    if ($phpPathReturn === 0 && ! empty($phpPathOutput)) {
        $detectedPhpPath = trim($phpPathOutput[0]);
        if (str_contains($detectedPhpPath, '/www/server/php/')) {
            $isBaotaEnv = true;
            $phpBinary = $detectedPhpPath;
        }
    }
}

foreach ($requiredExtensions as $extension) {
    // 使用多种方法检测扩展，与安装脚本保持一致
    $extensionLoaded = false;

    // 方法1: extension_loaded
    if (extension_loaded($extension)) {
        $extensionLoaded = true;
    } // 方法2: 检查 get_loaded_extensions
    elseif (in_array($extension, get_loaded_extensions())) {
        $extensionLoaded = true;
    } // 方法3: 对于 PDO 相关扩展的特殊处理
    elseif ($extension === 'pdo_mysql' && extension_loaded('pdo')) {
        $drivers = PDO::getAvailableDrivers();
        $extensionLoaded = in_array('mysql', $drivers);
    } // 方法4: 使用 PHP CLI 检测（如果 exec 可用）
    elseif ($execEnabled) {
        exec("$phpBinary -r \"echo extension_loaded('$extension') ? 'yes' : 'no';\" 2>&1", $output, $returnVar);
        if ($returnVar === 0 && isset($output[0]) && $output[0] === 'yes') {
            $extensionLoaded = true;
        }
        unset($output);
    }

    $extensionRequirements[] = [
        'name' => "PHP扩展: $extension",
        'value' => $extensionLoaded ? '已安装' : '未安装',
        'status' => $extensionLoaded ? 'success' : 'error',
    ];

    if (! $extensionLoaded) {
        $canProceed = false;
        $extensionSuccess = false;
        $errors[] = "缺少必要的PHP扩展: $extension";
    }
}

// 检查必需的PHP函数
$requiredFunctions = [
    'exec' => ['desc' => '执行外部程序，对于系统运行必不可少', 'error' => 'PHP exec函数被禁用，请在php.ini中启用它'],
    'putenv' => ['desc' => '设置环境变量，Laravel 应用配置需要此函数', 'error' => 'PHP putenv函数被禁用，请在php.ini中启用它'],
    'pcntl_signal' => ['desc' => '队列信号处理必需', 'error' => 'PHP pcntl_signal函数被禁用，队列工作进程需要此函数进行信号处理，请启用此函数'],
    'pcntl_alarm' => ['desc' => '队列超时处理必需', 'error' => 'PHP pcntl_alarm函数被禁用，队列工作进程需要此函数进行超时处理，请启用此函数'],
];

$requiredFunctionRequirements = [];
$requiredFunctionsSuccess = true;

foreach ($requiredFunctions as $funcName => $funcInfo) {
    $funcEnabled = function_exists($funcName) && ! in_array($funcName, array_map('trim', explode(',', ini_get('disable_functions'))));
    $requiredFunctionRequirements[] = [
        'name' => "PHP {$funcName}函数",
        'value' => $funcEnabled ? '可用' : '被禁用',
        'status' => $funcEnabled ? 'success' : 'error',
    ];

    if (! $funcEnabled) {
        $canProceed = false;
        $requiredFunctionsSuccess = false;
        $errors[] = $funcInfo['error'];
    }
}

// 检查可选的PHP函数
$optionalFunctions = [
    'proc_open' => [
        'desc' => '提升Composer性能', 'warning' => 'PHP proc_open函数被禁用。这不会阻止安装，但可能导致Composer使用备用解压方式，性能较慢且可能丢失UNIX权限。',
    ],
];

$optionalFunctionRequirements = [];
$optionalFunctionsHasWarnings = false;

foreach ($optionalFunctions as $funcName => $funcInfo) {
    $funcEnabled = function_exists($funcName) && ! in_array($funcName, array_map('trim', explode(',', ini_get('disable_functions'))));
    $optionalFunctionRequirements[] = [
        'name' => "PHP {$funcName}函数",
        'value' => $funcEnabled ? '可用' : '被禁用',
        'status' => $funcEnabled ? 'success' : 'warning',
    ];

    if (! $funcEnabled) {
        $optionalFunctionsHasWarnings = true;
        $warnings[] = $funcInfo['warning'];
    }
}

// 检查目录权限
$directoriesToCheck = [
    dirname(__DIR__).'/storage',
    dirname(__DIR__).'/bootstrap/cache',
];

$directoryRequirements = [];
$permissionsSuccess = true;

foreach ($directoriesToCheck as $directory) {
    $isWritable = is_writable($directory);
    $directoryRequirements[] = [
        'name' => '目录权限: '.basename($directory),
        'value' => $isWritable ? '可写' : '不可写',
        'status' => $isWritable ? 'success' : 'error',
    ];

    if (! $isWritable) {
        $canProceed = false;
        $permissionsSuccess = false;
        $errors[] = "目录 $directory 不可写";
    }
}

// 检查Composer是否已安装
$composerInstalled = false;
$composerVersion = 'N/A';

if ($execEnabled) {
    exec('composer --version 2>&1', $composerVersionOutput, $composerVersionReturnVar);
    if ($composerVersionReturnVar === 0) {
        $composerInstalled = true;
        // 获取Composer版本信息
        if (! empty($composerVersionOutput)) {
            // 解析Composer版本信息，格式如: "Composer version 2.8.1 2024-06-10 22:11:12"
            preg_match('/Composer version (\d+\.\d+(?:\.\d+)?)/', implode("\n", $composerVersionOutput), $matches);
            if (isset($matches[1])) {
                $composerVersion = $matches[1];
                // 检查版本是否低于2.8
                if (version_compare($composerVersion, '2.8.0', '<')) {
                    $warnings[] = "Composer版本 $composerVersion 低于推荐版本2.8，建议升级到最新版本以获得更好的性能和稳定性。";
                }
            } else {
                $composerVersion = htmlspecialchars($composerVersionOutput[0]);
            }
        }
    }
}

if (! $composerInstalled) {
    $canProceed = false;
    $errors[] = 'Composer未安装或无法执行';
}

// 检查Java是否可用
$javaInstalled = false;
$javaVersion = 'N/A';

if ($execEnabled) {
    exec('java -version 2>&1', $javaVersionOutput, $javaVersionReturnVar);
    if ($javaVersionReturnVar === 0) {
        $javaInstalled = true;
        // 获取Java版本信息
        if (! empty($javaVersionOutput)) {
            // 解析Java版本信息
            preg_match('/(?:version |openjdk version )"([^"]+)"/', implode("\n", $javaVersionOutput), $matches);
            if (isset($matches[1])) {
                $javaVersion = $matches[1];
                // 检查版本是否低于17
                // 提取主版本号进行比较
                preg_match('/^(\d+)/', $javaVersion, $majorVersionMatches);
                if (isset($majorVersionMatches[1])) {
                    $majorVersion = (int) $majorVersionMatches[1];
                    if ($majorVersion < 17) {
                        $warnings[] = "Java版本 $javaVersion 低于推荐版本17，建议升级到Java 17或更高版本以获得更好的性能和安全性。";
                    }
                }
            } else {
                $javaVersion = htmlspecialchars($javaVersionOutput[0]);
            }
        }
    }
}

if (! $javaInstalled) {
    $warnings[] = '未检测到Java命令。如果需要使用keytool生成JKS格式证书等功能，请确保JDK或JRE已正确安装并配置到系统PATH。';
}

// ===========================================
// 输出页面
// ===========================================

// 输出页面头部
echo file_get_contents(__DIR__.'/install-assets/header.html');

// 处理自删除请求
if (isset($_POST['self_delete'])) {
    $installDir = __DIR__.'/install-assets';
    $installFile = __FILE__;
    $projectRoot = dirname(__DIR__);

    // 创建清理脚本
    $cleanupScript = $projectRoot.'/cleanup_install.php';
    $cleanupContent = '<?php
// 延迟1秒后清理安装文件
sleep(1);

// 删除安装资源目录
if (is_dir("'.$installDir.'")) {
    function deleteDirectory($dir) {
        if (!is_dir($dir)) return false;
        $files = array_diff(scandir($dir), array(".", ".."));
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? deleteDirectory($path) : unlink($path);
        }
        return rmdir($dir);
    }
    deleteDirectory("'.$installDir.'");
}

// 删除安装主文件
if (file_exists("'.$installFile.'")) {
    unlink("'.$installFile.'");
}

// 删除自身
unlink(__FILE__);
?>';

    file_put_contents($cleanupScript, $cleanupContent);

    // 在后台执行清理脚本
    if (function_exists('exec')) {
        exec("php $cleanupScript > /dev/null 2>&1 &");
    }

    echo '<div class="requirement success">';
    echo '<h2>清理完成</h2>';
    echo '<strong>安装文件已删除</strong><br>';
    echo '安装文件和资源目录已被清理，页面将在3秒后自动关闭。';
    echo '</div>';
    echo '<script>setTimeout(function() { window.close(); }, 3000);</script>';
    echo file_get_contents(__DIR__.'/install-assets/footer.html');
    exit;
}

// 临时调试：显示检测结果
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    echo '<h2>调试信息</h2>';
    echo '<div class="requirement warning">';
    echo '<strong>调试模式激活</strong><br>';
    echo '.env文件存在: '.($envExists ? '是' : '否').'<br>';
    echo '系统已安装: '.($isInstalled ? '是' : '否').'<br>';

    if ($envExists) {
        $envContent = file_get_contents(dirname(__DIR__).'/.env');
        echo '<strong>.env文件内容预览:</strong><br>';
        echo '<pre>'.htmlspecialchars(substr($envContent, 0, 500)).'</pre>';

        // 详细的数据库检测
        echo '<strong>数据库连接测试:</strong><br>';
        try {
            // 重新解析.env文件
            $dbHost = null;
            $dbPort = null;
            $dbDatabase = null;
            $dbUsername = null;
            $dbPassword = '';

            if (preg_match('/DB_HOST=(.+)/', $envContent, $matches)) {
                $dbHost = trim($matches[1]);
            }
            if (preg_match('/DB_PORT=(.+)/', $envContent, $matches)) {
                $dbPort = trim($matches[1]);
            }
            if (preg_match('/DB_DATABASE=(.+)/', $envContent, $matches)) {
                $dbDatabase = trim($matches[1]);
            }
            if (preg_match('/DB_USERNAME=(.+)/', $envContent, $matches)) {
                $dbUsername = trim($matches[1]);
            }
            if (preg_match('/DB_PASSWORD=(.+)/', $envContent, $matches)) {
                $dbPassword = trim($matches[1]);
            }

            echo "数据库主机: $dbHost<br>";
            echo "数据库端口: $dbPort<br>";
            echo "数据库名称: $dbDatabase<br>";
            echo "数据库用户: $dbUsername<br>";
            echo '数据库密码: '.(empty($dbPassword) ? '空' : '***').'<br>';

            if (! empty($dbHost) && ! empty($dbPort) && ! empty($dbDatabase) && ! empty($dbUsername)) {
                try {
                    $dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbDatabase";
                    $pdo = new PDO($dsn, $dbUsername, $dbPassword, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_TIMEOUT => 3,
                    ]);

                    echo "<span style='color: green;'>✓ 数据库连接成功</span><br>";

                    // 检查管理员表
                    $adminsResult = $pdo->query("SHOW TABLES LIKE 'admins'");
                    if ($adminsResult && $adminsResult->rowCount() > 0) {
                        echo "<span style='color: green;'>✓ admins表存在</span><br>";

                        /** @noinspection SqlResolve */
                        $adminCount = $pdo->query('SELECT COUNT(*) FROM `admins`')->fetchColumn();
                        echo "管理员数量: $adminCount<br>";

                        if ($adminCount > 0) {
                            echo "<span style='color: green;'>✓ 管理员表有数据</span><br>";
                        } else {
                            echo "<span style='color: red;'>✗ 管理员表为空</span><br>";
                        }
                    } else {
                        echo "<span style='color: red;'>✗ admins表不存在</span><br>";
                    }

                    // 检查用户表
                    $usersResult = $pdo->query("SHOW TABLES LIKE 'users'");
                    if ($usersResult && $usersResult->rowCount() > 0) {
                        echo "<span style='color: green;'>✓ users表存在</span><br>";

                        /** @noinspection SqlResolve */
                        $userCount = $pdo->query('SELECT COUNT(*) FROM `users`')->fetchColumn();
                        echo "用户数量: $userCount<br>";

                        if ($userCount > 0) {
                            echo "<span style='color: green;'>✓ 用户表有数据</span><br>";
                        } else {
                            echo "<span style='color: red;'>✗ 用户表为空</span><br>";
                        }
                    } else {
                        echo "<span style='color: red;'>✗ users表不存在</span><br>";
                    }

                    // 检查其他表
                    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
                    echo '数据库中的表: '.implode(', ', $tables).'<br>';
                } catch (PDOException $e) {
                    echo "<span style='color: red;'>✗ 数据库连接失败: ".htmlspecialchars($e->getMessage()).'</span><br>';
                }
            } else {
                echo "<span style='color: red;'>✗ 数据库配置不完整</span><br>";
            }
        } catch (Exception $e) {
            echo "<span style='color: red;'>✗ 检测过程出错: ".htmlspecialchars($e->getMessage()).'</span><br>';
        }
    }
    echo '</div>';
    echo '<a href="?">返回正常模式</a><br><br>';
}

// 如果已经安装完成，显示已安装状态
if ($isInstalled) {
    echo '<h2>系统已安装</h2>';
    echo '<div class="requirement success">';
    echo '<strong>SSL证书管理系统已成功安装！</strong><br><br>';
    echo '系统已经完成安装并可以正常使用。<br>';
    echo '默认管理员账号: <strong>admin</strong><br>';
    echo '默认密码: <strong>123456</strong><br><br>';
    echo '请立即登录并修改默认密码！';
    echo '</div>';

    echo '<div class="requirement warning">';
    echo '<strong>安全提示:</strong> 为了系统安全，建议删除此安装文件。';
    echo '</div>';

    echo '<form method="post" style="margin-top: 20px;">';
    echo '<input type="hidden" name="self_delete" value="1">';
    echo '<button type="submit" class="btn" onclick="return confirm(\'确定要删除安装文件吗？删除后将无法再次访问此页面。\')">删除安装文件</button>';
    echo '</form>';

    echo file_get_contents(__DIR__.'/install-assets/footer.html');
    exit;
}

// 输出系统检查结果
if ($formStage == 'env') {
    // 判断系统环境检查是否都通过
    $systemCheckPassed = $phpVersionValid &&
                         $extensionSuccess &&
                         $requiredFunctionsSuccess &&
                         $permissionsSuccess &&
                         $composerInstalled;

    // 判断所有检查（包括配置）是否都通过
    $allChecksPassed = $systemCheckPassed && empty($errors);

    // 如果是POST提交后有配置错误，系统检查应该折叠
    // 如果是系统环境本身有问题，应该展开
    $isConfigError = isset($_POST['action']) && $_POST['action'] == 'config' && ! empty($errors);
    $shouldCollapseSystemCheck = $allChecksPassed || ($systemCheckPassed && $isConfigError);

    // 生成系统检查摘要内容
    $summaryContent = '';
    if ($systemCheckPassed) {
        // 系统检查通过
        if ($optionalFunctionsHasWarnings || ! $javaInstalled) {
            // 有警告
            $summaryContent = '<div class="requirement success" style="padding: 15px; margin: 10px 0;">
                ✓ 系统环境检查已通过，可以继续安装
            </div>
            <div class="requirement warning" style="padding: 15px; margin: 10px 0;">
                ⚠ 存在一些可选项警告，建议查看详情
            </div>';
        } else {
            // 完全通过
            $summaryContent = '<div class="requirement success" style="padding: 15px; margin: 10px 0;">
                ✓ 所有系统环境检查已通过，可以继续安装
            </div>';
        }
    } else {
        // 系统检查有错误
        $errorItems = [];
        if (! $phpVersionValid) {
            $errorItems[] = 'PHP版本不符合要求';
        }
        if (! $extensionSuccess) {
            $errorItems[] = '缺少必需的PHP扩展';
        }
        if (! $requiredFunctionsSuccess) {
            $errorItems[] = '必需的PHP函数被禁用';
        }
        if (! $permissionsSuccess) {
            $errorItems[] = '目录权限不足';
        }
        if (! $composerInstalled) {
            $errorItems[] = 'Composer未安装';
        }

        $errorList = implode('、', $errorItems);
        $summaryContent = '<div class="requirement error" style="padding: 15px; margin: 10px 0;">
            ✘ 系统环境检查未通过：'.$errorList.'<br>
            <small style="margin-top: 8px; display: block;">请点击"展开"查看详细信息并解决问题</small>
        </div>';
    }

    // 准备模板变量
    $templateVars = [
        'PHP_VERSION_STATUS' => $phpVersionValid ? 'success' : 'error',
        'PHP_VERSION' => $phpVersion,
        'REQUIRED_PHP_VERSION' => '>= '.$requiredPhpVersion,

        'PHP_EXTENSIONS_LIST' => TemplateHelper::generateRequirementsList($extensionRequirements),
        'PHP_EXTENSIONS_SUMMARY' => TemplateHelper::generateSummary(
            $extensionSuccess,
            false,
            '所有必需的PHP扩展已安装',
            '',
            '有一个或多个必要的PHP扩展缺失'
        ),

        'REQUIRED_PHP_FUNCTIONS_LIST' => TemplateHelper::generateRequirementsList($requiredFunctionRequirements),
        'REQUIRED_PHP_FUNCTIONS_SUMMARY' => TemplateHelper::generateSummary(
            $requiredFunctionsSuccess,
            false,
            '所有必需的PHP函数已启用',
            '',
            '有一个或多个必要的PHP函数被禁用'
        ),

        'OPTIONAL_PHP_FUNCTIONS_LIST' => TemplateHelper::generateRequirementsList($optionalFunctionRequirements),
        'OPTIONAL_PHP_FUNCTIONS_SUMMARY' => TemplateHelper::generateSummary(
            true,
            $optionalFunctionsHasWarnings,
            '所有可选PHP函数已启用',
            '有推荐函数被禁用，可能影响性能'
        ),

        'DIRECTORY_PERMISSIONS_LIST' => TemplateHelper::generateRequirementsList($directoryRequirements),
        'DIRECTORY_PERMISSIONS_SUMMARY' => TemplateHelper::generateSummary(
            $permissionsSuccess,
            false,
            '所有必需的目录权限已设置',
            '',
            '有一个或多个目录权限不足'
        ),

        'COMPOSER_STATUS' => $composerInstalled ? 'success' : 'error',
        'COMPOSER_VALUE' => $composerInstalled ? ('已安装 (版本: '.$composerVersion.')') : '未安装或无法执行',

        'JAVA_STATUS' => $javaInstalled ? 'success' : 'warning',
        'JAVA_VALUE' => $javaInstalled ? ('已安装 (版本: '.$javaVersion.')') : 'Java不可用',

        'ERRORS_SECTION' => TemplateHelper::generateMessageSection($errors),
        'WARNINGS_SECTION' => TemplateHelper::generateMessageSection($warnings, 'warning'),

        // 系统检查折叠控制
        'SYSTEM_CHECK_DISPLAY' => $shouldCollapseSystemCheck ? 'none' : 'block',
        'SYSTEM_CHECK_SUMMARY_DISPLAY' => $shouldCollapseSystemCheck ? 'block' : 'none',
        'SYSTEM_CHECK_TOGGLE_TEXT' => $shouldCollapseSystemCheck ? '展开' : '折叠',
        'SYSTEM_CHECK_SUMMARY_CONTENT' => $summaryContent,
    ];

    /** @noinspection PhpUnhandledExceptionInspection */
    echo TemplateHelper::render('system-check', $templateVars);

    // 显示配置表单（在配置阶段总是显示，让用户可以修正错误）
    if ($showConfigForm) {
        $configVars = [
            'DB_HOST' => htmlspecialchars($config['db_host']),
            'DB_PORT' => htmlspecialchars($config['db_port']),
            'DB_DATABASE' => htmlspecialchars($config['db_database']),
            'DB_USERNAME' => htmlspecialchars($config['db_username']),
            'DB_PASSWORD' => htmlspecialchars($config['db_password']),
            'REDIS_HOST' => htmlspecialchars($config['redis_host']),
            'REDIS_PORT' => htmlspecialchars($config['redis_port']),
            'REDIS_USERNAME' => htmlspecialchars($config['redis_username']),
            'REDIS_PASSWORD' => htmlspecialchars($config['redis_password']),
        ];

        /** @noinspection PhpUnhandledExceptionInspection */
        echo TemplateHelper::render('config-form', $configVars);
    }
} // 安装阶段
elseif ($formStage == 'install') {
    // 开始安装准备区域
    echo '<h2>安装准备</h2>';

    // 从 Session 读取连接状态来显示
    if ($_SESSION['install_db_connected'] ?? false) {
        echo '<div class="requirement success"><strong>数据库连接:</strong> 成功</div>';

        // 显示 MySQL 版本信息
        if (! empty($config['mysql_version'])) {
            echo '<div class="requirement info"><strong>MySQL 版本:</strong> '.$config['mysql_version'].'</div>';
            echo '<div class="requirement info"><strong>排序规则:</strong> '.$config['db_collation'].'</div>';
        }
        if (($_SESSION['install_db_empty']) === true) {
            echo '<div class="requirement success"><strong>数据库状态:</strong> 空数据库，可以安装</div>';
        } else {
            echo '<div class="requirement error"><strong>数据库状态:</strong> 数据库不为空 (在配置阶段检测到)，必须使用空数据库</div>';
            echo '<a href="'.$_SERVER['PHP_SELF'].'?action=reset" class="btn">返回重新配置</a>';
            $canProceed = false;
        }
    } else {
        echo '<div class="requirement error"><strong>数据库连接:</strong> 失败 (在配置阶段检测到)</div>';
        echo '<p>请返回上一步检查数据库配置</p>';
        echo '<a href="'.$_SERVER['PHP_SELF'].'?action=reset" class="btn">返回重新配置</a>';
        $canProceed = false;
    }

    // 显示安装表单或执行安装
    if ($canProceed) {
        if (! isset($_POST['install'])) {
            echo '<h2>开始安装</h2>';
            echo '<form method="post" id="install-form">';
            foreach ($config as $key => $value) {
                echo '<input type="hidden" name="'.htmlspecialchars($key).'" value="'.htmlspecialchars($value).'">';
            }
            echo '<div class="requirement success">';
            echo '<strong>将使用以下配置安装:</strong><br>';
            echo '数据库主机: '.htmlspecialchars($config['db_host']).'<br>';
            echo '数据库端口: '.htmlspecialchars($config['db_port']).'<br>';
            echo '数据库名称: '.htmlspecialchars($config['db_database']).'<br>';
            echo '数据库用户: '.htmlspecialchars($config['db_username']).'<br>';
            echo '</div>';

            echo '<input type="hidden" name="action" value="install">';
            echo '<input type="hidden" name="install" value="1">';
            echo '<button type="submit" id="install-button" class="btn" onclick="prepareAndSubmitInstall(this); return false;">立即安装</button>';
            echo '</form>';
            echo '<div id="install-log-div" class="log" style="display:none; margin-top: 20px;"></div>';
        } else {
            // 执行安装
            echo '<div id="install-log-div" class="log">正在执行安装...</div>';

            $totalSteps = 8;

            echo '<script>
                let logDiv = document.getElementById("install-log-div");
                while (logDiv.firstChild) {
                    logDiv.removeChild(logDiv.firstChild);
                }
            </script>';
            flush();

            try {
                chdir(dirname(__DIR__));
                $projectRoot = dirname(__DIR__);

                // 步骤1: 配置环境变量
                echo '<script>
                    let titleDiv = document.createElement("div");
                    titleDiv.innerHTML = "<strong>[1/'.$totalSteps.'] 配置环境变量...</strong>";
                    logDiv.appendChild(titleDiv);
                </script>';
                flush();

                $envTemplate = file_get_contents($projectRoot.'/.env.example');
                $envTemplate = preg_replace('/DB_HOST=.*/', 'DB_HOST='.$config['db_host'], $envTemplate);
                $envTemplate = preg_replace('/DB_PORT=.*/', 'DB_PORT='.$config['db_port'], $envTemplate);
                $envTemplate = preg_replace('/DB_DATABASE=.*/', 'DB_DATABASE='.$config['db_database'], $envTemplate);
                $envTemplate = preg_replace('/DB_USERNAME=.*/', 'DB_USERNAME='.$config['db_username'], $envTemplate);
                $envTemplate = preg_replace('/DB_PASSWORD=.*/', 'DB_PASSWORD='.$config['db_password'], $envTemplate);

                // 设置 DB_COLLATION（如果 .env.example 中没有，则添加）
                $dbCollation = $config['db_collation'] ?? 'utf8mb4_unicode_520_ci';
                if (str_contains($envTemplate, 'DB_COLLATION=')) {
                    $envTemplate = preg_replace('/DB_COLLATION=.*/', 'DB_COLLATION='.$dbCollation, $envTemplate);
                } else {
                    // 在 DB_PASSWORD 后面添加 DB_COLLATION
                    $envTemplate = preg_replace('/(DB_PASSWORD=.*)/', "$1\nDB_COLLATION=".$dbCollation, $envTemplate);
                }

                $envTemplate = preg_replace('/REDIS_HOST=.*/', 'REDIS_HOST='.$config['redis_host'], $envTemplate);
                $envTemplate = preg_replace('/REDIS_PORT=.*/', 'REDIS_PORT='.$config['redis_port'], $envTemplate);

                // 设置 Redis 认证信息
                if (str_contains($envTemplate, 'REDIS_USERNAME=')) {
                    $envTemplate = preg_replace('/REDIS_USERNAME=.*/', 'REDIS_USERNAME='.$config['redis_username'], $envTemplate);
                } else {
                    // 在 REDIS_PORT 后面添加 REDIS_USERNAME
                    $envTemplate = preg_replace('/(REDIS_PORT=.*)/', "$1\nREDIS_USERNAME=".$config['redis_username'], $envTemplate);
                }

                if (str_contains($envTemplate, 'REDIS_PASSWORD=')) {
                    $envTemplate = preg_replace('/REDIS_PASSWORD=.*/', 'REDIS_PASSWORD='.$config['redis_password'], $envTemplate);
                } else {
                    // 在 REDIS_USERNAME 后面添加 REDIS_PASSWORD
                    $envTemplate = preg_replace('/(REDIS_USERNAME=.*)/', "$1\nREDIS_PASSWORD=".$config['redis_password'], $envTemplate);
                }

                $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
                $appUrl = $host ? 'https://'.$host : '';
                $envTemplate = preg_replace('/APP_URL=.*/', 'APP_URL='.$appUrl, $envTemplate);

                file_put_contents($projectRoot.'/.env', $envTemplate);

                echo '<script>
                    let successDiv = document.createElement("div");
                    successDiv.className = "success";
                    successDiv.innerHTML = "✓ 环境变量配置完成";
                    logDiv.appendChild(successDiv);
                </script>';
                flush();
                $steps[] = '配置环境变量';

                // 步骤2: Composer安装
                echo '<script>
                    let titleDiv = document.createElement("div");
                    titleDiv.innerHTML = "<strong>[2/'.$totalSteps.'] 安装 Composer 依赖 (可能需要较长时间)...</strong>";
                    logDiv.appendChild(titleDiv);
                </script>';
                flush();

                exec('cd '.$projectRoot.' && composer install --no-interaction --no-dev --optimize-autoloader --no-scripts 2>&1', $composerOutput,
                    $composerReturnVar);

                $composerOutputHtml = htmlspecialchars(implode("\n", $composerOutput));
                echo '<script>
                    let preElement = document.createElement("pre");
                    preElement.className = "dark-output";
                    preElement.textContent = '.json_encode($composerOutputHtml).';
                    logDiv.appendChild(preElement);
                </script>';
                flush();

                if ($composerReturnVar === 0) {
                    echo '<script>
                        let successDiv = document.createElement("div");
                        successDiv.className = "success";
                        successDiv.innerHTML = "✓ Composer 依赖安装完成";
                        logDiv.appendChild(successDiv);
                    </script>';
                    flush();
                    $steps[] = '安装Composer依赖';
                } else {
                    throw new Exception('Composer 依赖安装失败 (返回码 '.$composerReturnVar.')');
                }

                // 步骤3: 生成应用密钥
                echo '<script>
                    let titleDiv = document.createElement("div");
                    titleDiv.innerHTML = "<strong>[3/'.$totalSteps.'] 生成应用密钥...</strong>";
                    logDiv.appendChild(titleDiv);
                </script>';
                flush();

                exec('php artisan key:generate --force 2>&1', $keyOutput, $keyReturnVar);

                if ($keyReturnVar === 0) {
                    echo '<script>
                        let successDiv = document.createElement("div");
                        successDiv.className = "success";
                        successDiv.innerHTML = "✓ 应用密钥生成完成";
                        logDiv.appendChild(successDiv);
                    </script>';
                    flush();
                    $steps[] = '生成应用密钥';
                } else {
                    throw new Exception('应用密钥生成失败 (返回码 '.$keyReturnVar.')');
                }

                // 步骤4: 生成JWT密钥
                echo '<script>
                    let titleDiv = document.createElement("div");
                    titleDiv.innerHTML = "<strong>[4/'.$totalSteps.'] 生成 JWT 密钥...</strong>";
                    logDiv.appendChild(titleDiv);
                </script>';
                flush();

                exec('php artisan jwt:secret --force 2>&1', $jwtOutput, $jwtReturnVar);

                if ($jwtReturnVar === 0) {
                    echo '<script>
                        let successDiv = document.createElement("div");
                        successDiv.className = "success";
                        successDiv.innerHTML = "✓ JWT 密钥生成完成";
                        logDiv.appendChild(successDiv);
                    </script>';
                    flush();
                    $steps[] = '生成JWT密钥';
                } else {
                    throw new Exception('JWT 密钥生成失败 (返回码 '.$jwtReturnVar.')');
                }

                // 步骤5: 数据库迁移
                if ($_SESSION['install_db_connected'] ?? false) {
                    echo '<script>
                        let titleDiv = document.createElement("div");
                        titleDiv.innerHTML = "<strong>[5/'.$totalSteps.'] 执行数据库迁移与数据填充...</strong>";
                        logDiv.appendChild(titleDiv);
                    </script>';
                    flush();

                    exec('php artisan migrate --force 2>&1', $migrateOutput, $migrateReturnVar);

                    $migrateOutputHtml = htmlspecialchars(implode("\n", $migrateOutput));
                    echo '<script>
                        let preElement = document.createElement("pre");
                        preElement.className = "dark-output";
                        preElement.textContent = '.json_encode($migrateOutputHtml).';
                        logDiv.appendChild(preElement);
                    </script>';
                    flush();

                    if ($migrateReturnVar === 0) {
                        echo '<script>
                            let successDiv = document.createElement("div");
                            successDiv.className = "success";
                            successDiv.innerHTML = "✓ 数据库迁移命令执行成功";
                            logDiv.appendChild(successDiv);
                        </script>';
                        flush();
                        $steps[] = '执行数据库迁移';

                        exec('php artisan db:seed --force 2>&1', $seedOutput, $seedReturnVar);

                        if ($seedReturnVar === 0) {
                            echo '<script>
                                let successDiv = document.createElement("div");
                                successDiv.className = "success";
                                successDiv.innerHTML = "✓ 数据填充命令执行成功";
                                logDiv.appendChild(successDiv);
                            </script>';
                            flush();
                            $steps[] = '执行数据填充';
                        }
                    } else {
                        throw new Exception('数据库迁移失败 (返回码 '.$migrateReturnVar.')');
                    }
                }

                // 步骤6: 优化配置
                echo '<script>
                    let titleDiv = document.createElement("div");
                    titleDiv.innerHTML = "<strong>[6/'.$totalSteps.'] 优化应用配置和路由...</strong>";
                    logDiv.appendChild(titleDiv);
                </script>';
                flush();

                exec('php artisan config:cache && php artisan route:cache 2>&1', $optimizeOutput, $optimizeReturnVar);

                if ($optimizeReturnVar === 0) {
                    echo '<script>
                        let successDiv = document.createElement("div");
                        successDiv.className = "success";
                        successDiv.innerHTML = "✓ 配置和路由缓存完成";
                        logDiv.appendChild(successDiv);
                    </script>';
                    flush();
                    $steps[] = '优化应用配置和路由';
                }

                // 步骤7: 清理安装文件
                echo '<script>
                    let titleDiv = document.createElement("div");
                    titleDiv.innerHTML = "<strong>[7/'.$totalSteps.'] 清理安装文件...</strong>";
                    logDiv.appendChild(titleDiv);
                </script>';
                flush();

                // 延迟清理，确保页面完全加载后再删除
                $installDir = __DIR__.'/install-assets';
                $installFile = __FILE__;

                // 创建清理脚本
                $cleanupScript = $projectRoot.'/cleanup_install.php';
                $cleanupContent = '<?php
// 延迟3秒后清理安装文件
sleep(3);

// 删除安装资源目录
if (is_dir("'.$installDir.'")) {
    function deleteDirectory($dir) {
        if (!is_dir($dir)) return false;
        $files = array_diff(scandir($dir), array(".", ".."));
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? deleteDirectory($path) : unlink($path);
        }
        return rmdir($dir);
    }
    deleteDirectory("'.$installDir.'");
}

// 删除安装主文件
if (file_exists("'.$installFile.'")) {
    unlink("'.$installFile.'");
}

// 删除自身
unlink(__FILE__);
?>';

                file_put_contents($cleanupScript, $cleanupContent);

                // 在后台执行清理脚本
                if (function_exists('exec')) {
                    exec("php $cleanupScript > /dev/null 2>&1 &");
                }

                echo '<script>
                    let successDiv = document.createElement("div");
                    successDiv.className = "success";
                    successDiv.innerHTML = "✓ 安装文件清理已安排";
                    logDiv.appendChild(successDiv);
                </script>';
                flush();
                $steps[] = '清理安装文件';

                // 完成安装
                echo '<h2>安装完成</h2>';
                echo '<div class="requirement success">';
                echo '安装过程已完成！系统已成功完成以下步骤：';
                echo '<ul>';
                foreach ($steps as $step) {
                    echo '<li>'.htmlspecialchars($step).'</li>';
                }
                echo '</ul>';
                echo '初始管理员账号: <strong>admin</strong><br>';
                echo '初始密码: <strong>123456</strong><br><br>';
                echo '请立即登录并修改默认密码！';
                echo '</div>';

                echo '<div class="requirement success">';
                echo '<strong>安全提示：</strong> 安装文件和资源目录已自动清理。';
                echo '</div>';

                if (! $javaInstalled) {
                    echo '<div class="requirement warning">';
                    echo '<strong>Java提示：</strong> 未检测到Java命令。如果需要使用keytool生成JKS格式证书等功能，请确保JDK或JRE已正确安装并配置到系统PATH。';
                    echo '</div>';
                }
            } catch (Exception $e) {
                $errorMessage = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                echo '<script>
                    let errorDiv = document.createElement("div");
                    errorDiv.className = "error";
                    errorDiv.innerHTML = "✘ 安装失败: '.$errorMessage.'";
                    logDiv.appendChild(errorDiv);
                </script>';
                flush();
            }
        }
    }
}

// 输出页面底部
echo file_get_contents(__DIR__.'/install-assets/footer.html');
