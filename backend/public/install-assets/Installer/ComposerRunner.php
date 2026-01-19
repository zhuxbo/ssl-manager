<?php

namespace Install\Installer;

/**
 * Composer 安装执行器
 */
class ComposerRunner
{
    private string $projectRoot;

    private array $output = [];

    private int $returnCode = -1;

    private bool $mirrorConfigured = false;

    private ?string $phpBinary = null;

    private ?string $composerPath = null;

    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 3);
        $this->detectPhpAndComposer();
    }

    /**
     * 检测 PHP 和 Composer 路径
     * 宝塔环境需要使用宝塔的 PHP（仅支持 8.3/8.4）
     */
    private function detectPhpAndComposer(): void
    {
        // 优先从 PHP_BINARY 推断 PHP CLI 路径
        // PHP_BINARY 可能是 php-fpm 路径，需要转换为 cli 路径
        $currentBinary = PHP_BINARY;

        // 检测宝塔环境：如果当前 PHP 路径包含 /www/server/php/
        if (str_contains($currentBinary, '/www/server/php/')) {
            // 从 php-fpm 路径推断 CLI 路径
            // /www/server/php/83/sbin/php-fpm -> /www/server/php/83/bin/php
            $cliPath = preg_replace('#/sbin/php-fpm$#', '/bin/php', $currentBinary);
            $cliPath = preg_replace('#/bin/php-fpm$#', '/bin/php', $cliPath);
            $this->phpBinary = $cliPath;
            $this->output[] = "[PHP] 宝塔环境 CLI: $cliPath";
        } elseif (str_contains($currentBinary, 'php-fpm')) {
            // 非宝塔环境的 php-fpm，尝试转换
            $cliPath = str_replace(['sbin/php-fpm', 'bin/php-fpm'], 'bin/php', $currentBinary);
            $this->phpBinary = $cliPath;
            $this->output[] = "[PHP] 从 FPM 推断 CLI: $cliPath";
        } else {
            // 已经是 CLI
            $this->phpBinary = $currentBinary;
            $this->output[] = "[PHP] 使用: $currentBinary";
        }

        // 检测 Composer 路径
        foreach (['/usr/local/bin/composer', '/usr/bin/composer'] as $path) {
            if (@file_exists($path)) {
                $this->composerPath = $path;
                break;
            }
        }

        // 回退：使用 which 查找
        if (! $this->composerPath) {
            $this->composerPath = trim(shell_exec('which composer 2>/dev/null') ?? '');
        }

        if ($this->composerPath) {
            $this->output[] = '[Composer] 路径: ' . $this->composerPath;
        }
    }

    /**
     * 获取 Composer 命令
     */
    private function getComposerCommand(): string
    {
        if ($this->composerPath && $this->phpBinary) {
            // 使用指定的 PHP 运行 Composer
            return escapeshellarg($this->phpBinary) . ' ' . escapeshellarg($this->composerPath);
        }

        // 回退：直接使用 composer 命令
        return 'composer';
    }

    /**
     * 执行 composer install
     */
    public function install(): bool
    {
        $this->returnCode = -1;

        // 自动检测并配置镜像
        $this->configureComposerMirror();

        // 执行安装
        $success = $this->runComposerInstall();

        // 安装完成后重置镜像配置
        if ($this->mirrorConfigured) {
            $this->resetComposerMirror();
        }

        return $success;
    }

    /**
     * 执行 composer install 命令
     */
    private function runComposerInstall(): bool
    {
        $composerCmd = $this->getComposerCommand();
        // 设置 HOME 和 COMPOSER_HOME 环境变量，避免缓存目录权限问题
        $homeDir = getenv('HOME') ?: '/tmp';
        $env = "HOME=" . escapeshellarg($homeDir) . " COMPOSER_HOME=" . escapeshellarg($homeDir . '/.composer');
        $command = "$env $composerCmd install --no-interaction --no-dev --optimize-autoloader --no-scripts -d " . escapeshellarg($this->projectRoot) . " 2>&1";

        $this->output[] = "[Command] $command";
        exec($command, $this->output, $this->returnCode);

        return $this->returnCode === 0;
    }

    /**
     * 检测网络并配置 Composer 镜像
     */
    private function configureComposerMirror(): void
    {
        // 1. 优先从 version.json 读取网络配置（用户安装时选择）
        $versionFile = $this->projectRoot . '/version.json';
        if (file_exists($versionFile)) {
            $versionData = json_decode(file_get_contents($versionFile), true);
            if (isset($versionData['network'])) {
                $network = $versionData['network'];
                if ($network === 'china') {
                    $this->output[] = '[Mirror] 从 version.json 读取配置: 使用国内镜像';
                    $this->setChinaMirror();

                    return;
                }
                if ($network === 'global') {
                    $this->output[] = '[Mirror] 从 version.json 读取配置: 使用国际源';

                    return;
                }
            }
        }

        // 2. 检查环境变量强制指定
        $forceMirror = getenv('FORCE_CHINA_MIRROR');
        if ($forceMirror !== false) {
            if ($forceMirror === '0') {
                $this->output[] = '[Mirror] FORCE_CHINA_MIRROR=0, 使用默认源';

                return;
            }
            if ($forceMirror === '1') {
                $this->output[] = '[Mirror] FORCE_CHINA_MIRROR=1, 强制使用国内镜像';
                $this->setChinaMirror();

                return;
            }
        }

        // 3. 回退到自动检测（兼容旧版本）
        if ($this->isChinaServer()) {
            $this->output[] = '[Mirror] 自动检测到中国大陆网络环境，使用国内镜像';
            $this->setChinaMirror();

            return;
        }

        $this->output[] = '[Mirror] 使用默认源';
    }

    /**
     * 检测是否为中国大陆服务器
     * 多层检测确保准确性
     */
    private function isChinaServer(): bool
    {
        // 1. 云服务商元数据检测 - 阿里云
        $aliyunRegion = $this->fetchUrl('http://100.100.100.200/latest/meta-data/region-id', 1);
        if ($aliyunRegion && str_starts_with($aliyunRegion, 'cn-')) {
            $this->output[] = '[Mirror] 检测到阿里云中国区域: ' . $aliyunRegion;

            return true;
        }

        // 2. 云服务商元数据检测 - 腾讯云
        $tencentRegion = $this->fetchUrl('http://metadata.tencentyun.com/latest/meta-data/region', 1);
        if ($tencentRegion) {
            $chinaRegions = ['ap-beijing', 'ap-shanghai', 'ap-guangzhou', 'ap-chengdu', 'ap-chongqing', 'ap-nanjing'];
            foreach ($chinaRegions as $region) {
                if (str_starts_with($tencentRegion, $region)) {
                    $this->output[] = '[Mirror] 检测到腾讯云中国区域: ' . $tencentRegion;

                    return true;
                }
            }
            // 如果是腾讯云但不是中国区域，则不是中国服务器
            return false;
        }

        // 3. 云服务商元数据检测 - 华为云
        $huaweiMeta = $this->fetchUrl('http://169.254.169.254/openstack/latest/meta_data.json', 1);
        if ($huaweiMeta && str_contains($huaweiMeta, 'cn-')) {
            $this->output[] = '[Mirror] 检测到华为云中国区域';

            return true;
        }

        // 4. 百度可达 + Google 不可达检测
        $baiduOk = $this->checkNetworkAccess('https://www.baidu.com', 2);
        if ($baiduOk) {
            $googleOk = $this->checkNetworkAccess('https://www.google.com', 3);
            if (! $googleOk) {
                $this->output[] = '[Mirror] 百度可达但 Google 不可达，判定为中国大陆网络';

                return true;
            }
        }

        // 5. GitHub API 快速访问检测（如果 GitHub 很慢，也使用镜像）
        if (! $this->checkNetworkAccess('https://api.github.com', 3)) {
            $this->output[] = '[Mirror] GitHub API 访问缓慢，使用国内镜像';

            return true;
        }

        return false;
    }

    /**
     * 设置中国镜像
     */
    private function setChinaMirror(): void
    {
        $composerCmd = $this->getComposerCommand();
        $projectRoot = escapeshellarg($this->projectRoot);

        // 1. 配置阿里云 Composer 镜像
        $configCmd = "cd $projectRoot && $composerCmd config repo.packagist composer https://mirrors.aliyun.com/composer/ 2>&1";
        exec($configCmd, $output, $returnCode);

        if ($returnCode === 0) {
            $this->mirrorConfigured = true;
            $this->output[] = '[Mirror] 已配置阿里云 Composer 镜像';
        }

        // 2. 设置超时时间（180 秒，超时后使用 Gitee zip 备用下载）
        exec("cd $projectRoot && $composerCmd config process-timeout 180 2>&1");
        exec("cd $projectRoot && $composerCmd config github-protocols https 2>&1");
        $this->output[] = '[Mirror] 已设置超时时间 180 秒（超时将自动从 Gitee 下载）';
    }

    /**
     * 重置 Composer 镜像配置
     */
    private function resetComposerMirror(): void
    {
        $composerCmd = $this->getComposerCommand();
        $resetCmd = 'cd ' . escapeshellarg($this->projectRoot)
            . " && $composerCmd config --unset repo.packagist 2>&1";

        exec($resetCmd);
    }

    /**
     * 检测网络访问（HEAD 请求）
     */
    private function checkNetworkAccess(string $url, int $timeout = 3): bool
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_NOBODY => true,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode >= 200 && $httpCode < 400;
    }

    /**
     * 获取 URL 内容
     */
    private function fetchUrl(string $url, int $timeout = 3): ?string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 400 && $result !== false) {
            return trim($result);
        }

        return null;
    }

    /**
     * 获取执行输出
     */
    public function getOutput(): array
    {
        return $this->output;
    }

    /**
     * 获取输出字符串
     */
    public function getOutputString(): string
    {
        return implode("\n", $this->output);
    }

    /**
     * 获取返回码
     */
    public function getReturnCode(): int
    {
        return $this->returnCode;
    }
}
