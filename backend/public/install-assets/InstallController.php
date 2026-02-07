<?php

namespace Install;

use Install\Checker\RequirementChecker;
use Install\Connector\DatabaseConnector;
use Install\DTO\InstallConfig;
use Install\Installer\Cleaner;
use Install\Installer\InstallExecutor;
use Install\View\Renderer;

/**
 * 安装控制器
 */
class InstallController
{
    private string $projectRoot;

    private Renderer $renderer;

    private RequirementChecker $requirementChecker;

    private DatabaseConnector $databaseConnector;

    private InstallConfig $config;

    private array $errors = [];

    private array $warnings = [];

    private bool $canProceed = true;

    private string $stage = 'env';

    private bool $isInstalled = false;

    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 2);
        $this->renderer = new Renderer;
        $this->requirementChecker = new RequirementChecker($this->projectRoot);
        $this->databaseConnector = new DatabaseConnector;
        $this->config = InstallConfig::fromSession();
    }

    /**
     * 处理请求
     */
    public function handleRequest(): void
    {
        // 检查安装状态
        $this->checkInstallStatus();

        // 处理表单提交
        $this->handlePost();

        // 渲染页面
        $this->render();
    }

    /**
     * 检查安装状态
     */
    private function checkInstallStatus(): void
    {
        $envFile = $this->projectRoot.'/.env';

        if (! file_exists($envFile)) {
            return;
        }

        // 读取 .env 文件获取数据库配置
        $envContent = file_get_contents($envFile);

        $dbHost = $this->parseEnvValue($envContent, 'DB_HOST');
        $dbPort = $this->parseEnvValue($envContent, 'DB_PORT');
        $dbDatabase = $this->parseEnvValue($envContent, 'DB_DATABASE');
        $dbUsername = $this->parseEnvValue($envContent, 'DB_USERNAME');
        $dbPassword = $this->parseEnvValue($envContent, 'DB_PASSWORD');

        if (empty($dbHost) || empty($dbDatabase) || empty($dbUsername)) {
            $this->canProceed = false;
            $this->errors[] = '检测到已存在的 .env 文件，但系统未完成安装。为避免覆盖现有配置，安装程序已停止。如需重新安装，请先删除根目录中的 .env 文件。';

            return;
        }

        $testConfig = new InstallConfig(
            dbHost: $dbHost,
            dbPort: (int) ($dbPort ?: 3306),
            dbDatabase: $dbDatabase,
            dbUsername: $dbUsername,
            dbPassword: $dbPassword,
        );

        if ($this->databaseConnector->test($testConfig) && $this->databaseConnector->isInstalled()) {
            $this->isInstalled = true;
        } else {
            $this->canProceed = false;
            $this->errors[] = '检测到已存在的 .env 文件，但系统未完成安装。为避免覆盖现有配置，安装程序已停止。如需重新安装，请先删除根目录中的 .env 文件。';
        }
    }

    /**
     * 解析 .env 文件中的值
     */
    private function parseEnvValue(string $content, string $key): ?string
    {
        if (preg_match('/'.$key.'=(.*)/', $content, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * 处理 POST 请求
     */
    private function handlePost(): void
    {
        if ($this->isInstalled) {
            // 处理自删除请求
            if (isset($_POST['self_delete'])) {
                $this->handleSelfDelete();
            }

            return;
        }

        if (! isset($_POST['action'])) {
            return;
        }

        $action = $_POST['action'];

        if ($action === 'config') {
            $this->handleConfigSubmit();
        } elseif ($action === 'install') {
            $this->stage = 'install';

            if (isset($_POST['install'])) {
                // 检测是否为 AJAX 请求
                if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
                    $this->executeInstallStream();
                    exit;
                }
                $this->handleInstall();
            }
        }
    }

    /**
     * 处理配置表单提交
     */
    private function handleConfigSubmit(): void
    {
        $this->config = InstallConfig::fromPost();
        $this->config->toSession();

        // 验证数据库配置
        if (! $this->config->isDatabaseConfigComplete()) {
            $this->errors[] = '数据库配置不完整，请填写必要的数据库信息';
            $this->canProceed = false;

            return;
        }

        // 测试数据库连接
        if (! $this->databaseConnector->test($this->config)) {
            $this->errors[] = $this->databaseConnector->getError();
            $this->canProceed = false;
            $_SESSION['install_db_connected'] = false;
            $_SESSION['install_db_empty'] = false;

            return;
        }

        $_SESSION['install_db_connected'] = true;

        // 检测 MySQL 版本并设置 collation
        $this->databaseConnector->detectVersion($this->config);
        $this->config->toSession();

        // 检查数据库是否为空
        if (! $this->databaseConnector->isEmpty()) {
            $tables = $this->databaseConnector->getTables();
            $tablesList = implode(', ', $tables);
            $this->errors[] = '数据库不为空，包含 '.count($tables).' 个表 ('
                .(strlen($tablesList) > 100 ? substr($tablesList, 0, 100).'...' : $tablesList)
                .')，必须使用空数据库进行安装';
            $this->canProceed = false;
            $_SESSION['install_db_empty'] = false;

            return;
        }

        $_SESSION['install_db_empty'] = true;

        // 所有检查通过，进入安装阶段
        $this->stage = 'install';
    }

    /**
     * 处理安装执行
     */
    private function handleInstall(): void
    {
        $this->config = InstallConfig::fromPost();
        $this->config->toSession();
    }

    /**
     * 处理自删除请求
     */
    private function handleSelfDelete(): void
    {
        $cleaner = new Cleaner;

        if ($cleaner->scheduleCleanup()) {
            $this->renderer->header();

            echo '<div class="requirement success">';
            echo '<h2>清理完成</h2>';
            echo '<strong>安装文件已删除</strong><br>';
            echo '安装文件和资源目录已被清理，页面将在3秒后自动关闭。';
            echo '</div>';
            echo '<script>setTimeout(function() { window.close(); }, 3000);</script>';

            $this->renderer->footer();
            exit;
        }
    }

    /**
     * 渲染页面
     */
    private function render(): void
    {
        $this->renderer->header();

        // 调试模式
        if (isset($_GET['debug']) && $_GET['debug'] === '1') {
            $this->renderDebug();
        }

        // 已安装状态
        if ($this->isInstalled) {
            $this->renderInstalled();
            $this->renderer->footer();

            return;
        }

        // 系统检查
        $checkResult = $this->requirementChecker->checkAll();

        if (! $checkResult['canProceed']) {
            $this->canProceed = false;
            $this->errors = array_merge($this->errors, $checkResult['errors']);
        }

        $this->warnings = array_merge($this->warnings, $checkResult['warnings']);

        // 渲染对应阶段
        if ($this->stage === 'env') {
            $this->renderEnvStage($checkResult);
        } elseif ($this->stage === 'install') {
            $this->renderInstallStage();
        }

        $this->renderer->footer();
    }

    /**
     * 渲染环境检查阶段
     */
    private function renderEnvStage(array $checkResult): void
    {
        $details = $checkResult['details'];

        // 判断系统环境检查是否都通过
        $systemCheckPassed = $details['phpVersion']->success
            && $details['extensions']['success']
            && $details['requiredFunctions']['success']
            && $details['permissions']['success']
            && $details['composer']->success;

        // 判断所有检查是否都通过
        $allChecksPassed = $systemCheckPassed && empty($this->errors);

        // 是否折叠系统检查
        $isConfigError = isset($_POST['action']) && $_POST['action'] === 'config' && ! empty($this->errors);
        $shouldCollapse = $allChecksPassed || ($systemCheckPassed && $isConfigError);

        // 生成系统检查摘要
        $hasWarnings = $details['optionalFunctions']['hasWarnings']
            || $details['java']->status === 'warning';

        $errorItems = [];
        if (! $details['phpVersion']->success) {
            $errorItems[] = 'PHP版本不符合要求';
        }
        if (! $details['extensions']['success']) {
            $errorItems[] = '缺少必需的PHP扩展';
        }
        if (! $details['requiredFunctions']['success']) {
            $errorItems[] = '必需的PHP函数被禁用';
        }
        if (! $details['permissions']['success']) {
            $errorItems[] = '目录权限不足';
        }
        if (! $details['composer']->success) {
            $errorItems[] = 'Composer未安装';
        }

        $summaryContent = $this->renderer->systemCheckSummary($systemCheckPassed, $hasWarnings, $errorItems);

        // 渲染系统检查模板
        $templateVars = [
            'PHP_VERSION_STATUS' => $details['phpVersion']->status,
            'PHP_VERSION' => $details['phpVersion']->value,
            'REQUIRED_PHP_VERSION' => '>= 8.3.0',
            'PHP_EXTENSIONS_LIST' => $this->renderer->requirementsList($details['extensions']['results']),
            'PHP_EXTENSIONS_SUMMARY' => $this->renderer->summary(
                $details['extensions']['success'],
                false,
                '所有必需的PHP扩展已安装',
                '',
                '有一个或多个必要的PHP扩展缺失'
            ),
            'REQUIRED_PHP_FUNCTIONS_LIST' => $this->renderer->requirementsList($details['requiredFunctions']['results']),
            'REQUIRED_PHP_FUNCTIONS_SUMMARY' => $this->renderer->summary(
                $details['requiredFunctions']['success'],
                false,
                '所有必需的PHP函数已启用',
                '',
                '有一个或多个必要的PHP函数被禁用'
            ),
            'OPTIONAL_PHP_FUNCTIONS_LIST' => $this->renderer->requirementsList($details['optionalFunctions']['results']),
            'OPTIONAL_PHP_FUNCTIONS_SUMMARY' => $this->renderer->summary(
                true,
                $details['optionalFunctions']['hasWarnings'],
                '所有可选PHP函数已启用',
                '有推荐函数被禁用，可能影响性能'
            ),
            'DIRECTORY_PERMISSIONS_LIST' => $this->renderer->requirementsList($details['permissions']['results']),
            'DIRECTORY_PERMISSIONS_SUMMARY' => $this->renderer->summary(
                $details['permissions']['success'],
                false,
                '所有必需的目录权限已设置',
                '',
                '有一个或多个目录权限不足'
            ),
            'COMPOSER_STATUS' => $details['composer']->status,
            'COMPOSER_VALUE' => $details['composer']->value,
            'JAVA_STATUS' => $details['java']->status,
            'JAVA_VALUE' => $details['java']->value,
            'ERRORS_SECTION' => $this->renderer->messageSection($this->errors),
            'WARNINGS_SECTION' => $this->renderer->messageSection($this->warnings, 'warning'),
            'SYSTEM_CHECK_DISPLAY' => $shouldCollapse ? 'none' : 'block',
            'SYSTEM_CHECK_SUMMARY_DISPLAY' => $shouldCollapse ? 'block' : 'none',
            'SYSTEM_CHECK_TOGGLE_TEXT' => $shouldCollapse ? '展开' : '折叠',
            'SYSTEM_CHECK_SUMMARY_CONTENT' => $summaryContent,
        ];

        echo $this->renderer->render('system-check', $templateVars);

        // 渲染配置表单
        $configVars = [
            'DB_HOST' => htmlspecialchars($this->config->dbHost),
            'DB_PORT' => htmlspecialchars((string) $this->config->dbPort),
            'DB_DATABASE' => htmlspecialchars($this->config->dbDatabase),
            'DB_USERNAME' => htmlspecialchars($this->config->dbUsername),
            'DB_PASSWORD' => htmlspecialchars($this->config->dbPassword),
        ];

        echo $this->renderer->render('config-form', $configVars);
    }

    /**
     * 渲染安装阶段
     */
    private function renderInstallStage(): void
    {
        echo '<h2>安装准备</h2>';

        // 显示连接状态
        if ($_SESSION['install_db_connected'] ?? false) {
            echo '<div class="requirement success"><strong>数据库连接:</strong> 成功</div>';

            if (! empty($this->config->mysqlVersion)) {
                echo '<div class="requirement info"><strong>MySQL 版本:</strong> '.$this->config->mysqlVersion.'</div>';
                echo '<div class="requirement info"><strong>排序规则:</strong> '.$this->config->dbCollation.'</div>';
            }

            if ($_SESSION['install_db_empty'] ?? false) {
                echo '<div class="requirement success"><strong>数据库状态:</strong> 空数据库，可以安装</div>';
            } else {
                echo '<div class="requirement error"><strong>数据库状态:</strong> 数据库不为空，必须使用空数据库</div>';
                echo '<a href="'.$_SERVER['PHP_SELF'].'" class="btn">返回重新配置</a>';
                $this->canProceed = false;

                return;
            }
        } else {
            echo '<div class="requirement error"><strong>数据库连接:</strong> 失败</div>';
            echo '<p>请返回上一步检查数据库配置</p>';
            echo '<a href="'.$_SERVER['PHP_SELF'].'" class="btn">返回重新配置</a>';
            $this->canProceed = false;

            return;
        }

        // 显示安装表单或执行安装
        if (! isset($_POST['install'])) {
            $this->renderInstallForm();
        } else {
            $this->executeInstall();
        }
    }

    /**
     * 渲染安装表单
     */
    private function renderInstallForm(): void
    {
        echo '<h2>开始安装</h2>';
        echo '<form method="post" id="install-form">';

        // 隐藏配置字段
        foreach ($this->config->toArray() as $key => $value) {
            $key = str_replace('_', '_', $key);
            echo '<input type="hidden" name="'.htmlspecialchars($key).'" value="'.htmlspecialchars((string) $value).'">';
        }

        echo '<div class="requirement success">';
        echo '<strong>将使用以下配置安装:</strong><br>';
        echo '数据库主机: '.htmlspecialchars($this->config->dbHost).'<br>';
        echo '数据库端口: '.htmlspecialchars((string) $this->config->dbPort).'<br>';
        echo '数据库名称: '.htmlspecialchars($this->config->dbDatabase).'<br>';
        echo '数据库用户: '.htmlspecialchars($this->config->dbUsername).'<br>';
        echo '</div>';

        echo '<input type="hidden" name="action" value="install">';
        echo '<input type="hidden" name="install" value="1">';
        echo '<div id="install-log-div" class="log" style="display:none; margin-top: 20px;"></div>';
        echo '<button type="submit" id="install-button" class="btn" style="margin-top: 20px;" onclick="prepareAndSubmitInstall(this); return false;">立即安装</button>';
        echo '</form>';
    }

    /**
     * 执行安装
     */
    private function executeInstall(): void
    {
        echo '<div id="install-log-div" class="log">正在执行安装...</div>';

        $executor = new InstallExecutor($this->projectRoot);
        $reporter = $executor->getReporter();

        $reporter->init();
        $result = $executor->execute($this->config);

        if ($result['success']) {
            $this->renderInstallSuccess($result['steps']);
        }
    }

    /**
     * 执行安装（流模式，用于 AJAX 请求）
     */
    private function executeInstallStream(): void
    {
        // 从 POST 数据重建配置
        $this->config = InstallConfig::fromPost();

        $executor = new InstallExecutor($this->projectRoot);
        $reporter = $executor->getReporter();

        // 启用流模式
        $reporter->setStreamMode(true);

        $result = $executor->execute($this->config);

        if ($result['success']) {
            // 发送成功事件
            $reporter->sendSuccess($result['steps']);
        }
    }

    /**
     * 渲染安装成功页面
     */
    private function renderInstallSuccess(array $steps): void
    {
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

        // Java 警告
        $toolChecker = $this->requirementChecker->getToolChecker();
        if (! $toolChecker->isJavaAvailable()) {
            echo '<div class="requirement warning">';
            echo '<strong>Java提示：</strong> 未检测到Java命令。如果需要使用keytool生成JKS格式证书等功能，请确保JDK或JRE已正确安装并配置到系统PATH。';
            echo '</div>';
        }
    }

    /**
     * 渲染已安装状态
     */
    private function renderInstalled(): void
    {
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
    }

    /**
     * 渲染调试信息
     */
    private function renderDebug(): void
    {
        echo '<h2>调试信息</h2>';
        echo '<div class="requirement warning">';
        echo '<strong>调试模式激活</strong><br>';

        $envFile = $this->projectRoot.'/.env';
        echo '.env文件存在: '.(file_exists($envFile) ? '是' : '否').'<br>';
        echo '系统已安装: '.($this->isInstalled ? '是' : '否').'<br>';

        if (file_exists($envFile)) {
            $envContent = file_get_contents($envFile);
            echo '<strong>.env文件内容预览:</strong><br>';
            echo '<pre>'.htmlspecialchars(substr($envContent, 0, 500)).'</pre>';
        }

        echo '</div>';
        echo '<a href="?">返回正常模式</a><br><br>';
    }
}
