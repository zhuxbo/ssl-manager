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

    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 3);
    }

    /**
     * 执行 composer install
     */
    public function install(): bool
    {
        $this->output = [];
        $this->returnCode = -1;

        // 自动检测并配置镜像
        $this->configureComposerMirror();

        $command = 'cd ' . escapeshellarg($this->projectRoot)
            . ' && composer install --no-interaction --no-dev --optimize-autoloader --no-scripts 2>&1';

        exec($command, $this->output, $this->returnCode);

        // 安装完成后重置镜像配置
        if ($this->mirrorConfigured) {
            $this->resetComposerMirror();
        }

        return $this->returnCode === 0;
    }

    /**
     * 检测网络并配置 Composer 镜像
     */
    private function configureComposerMirror(): void
    {
        // 检查环境变量强制指定
        $forceMirror = getenv('FORCE_CHINA_MIRROR');
        if ($forceMirror !== false) {
            if ($forceMirror === '0') {
                $this->output[] = '[Mirror] FORCE_CHINA_MIRROR=0, using default source';

                return;
            }
            if ($forceMirror === '1') {
                $this->output[] = '[Mirror] FORCE_CHINA_MIRROR=1, forcing Aliyun mirror';
                $this->setAliyunMirror();

                return;
            }
        }

        // 检测是否能快速访问 GitHub API
        if ($this->checkNetworkAccess('https://api.github.com', 3)) {
            return;
        }

        // 配置阿里云镜像
        $this->output[] = '[Mirror] GitHub API slow, switching to Aliyun mirror';
        $this->setAliyunMirror();
    }

    /**
     * 设置阿里云镜像
     */
    private function setAliyunMirror(): void
    {
        $configCmd = 'cd ' . escapeshellarg($this->projectRoot)
            . ' && composer config repo.packagist composer https://mirrors.aliyun.com/composer/ 2>&1';

        exec($configCmd, $output, $returnCode);

        if ($returnCode === 0) {
            $this->mirrorConfigured = true;
            $this->output[] = '[Mirror] Configured Aliyun composer mirror';
        }
    }

    /**
     * 重置 Composer 镜像配置
     */
    private function resetComposerMirror(): void
    {
        $resetCmd = 'cd ' . escapeshellarg($this->projectRoot)
            . ' && composer config --unset repo.packagist 2>&1';

        exec($resetCmd);
    }

    /**
     * 检测网络访问
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
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode >= 200 && $httpCode < 400;
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
