<?php

namespace Install\View;

/**
 * 安装进度报告器
 */
class ProgressReporter
{
    private int $totalSteps;

    public function __construct(int $totalSteps = 8)
    {
        $this->totalSteps = $totalSteps;
    }

    /**
     * 初始化日志区域
     */
    public function init(): void
    {
        echo '<script>
            let logDiv = document.getElementById("install-log-div");
            while (logDiv.firstChild) {
                logDiv.removeChild(logDiv.firstChild);
            }
        </script>';
        $this->flush();
    }

    /**
     * 开始一个步骤
     */
    public function startStep(int $step, string $title): void
    {
        $escapedTitle = htmlspecialchars($title);

        echo '<script>
            (function() {
                let logDiv = document.getElementById("install-log-div");
                let titleDiv = document.createElement("div");
                titleDiv.innerHTML = "<strong>[' . $step . '/' . $this->totalSteps . '] ' . $escapedTitle . '...</strong>";
                titleDiv.style.marginTop = "15px";
                logDiv.appendChild(titleDiv);
            })();
        </script>';
        $this->flush();
    }

    /**
     * 完成一个步骤
     */
    public function completeStep(string $message): void
    {
        $escapedMessage = htmlspecialchars($message);

        echo '<script>
            (function() {
                let logDiv = document.getElementById("install-log-div");
                let successDiv = document.createElement("div");
                successDiv.className = "success";
                successDiv.innerHTML = "✓ ' . $escapedMessage . '";
                logDiv.appendChild(successDiv);
            })();
        </script>';
        $this->flush();
    }

    /**
     * 显示命令输出
     */
    public function showOutput(string $output): void
    {
        $escapedOutput = htmlspecialchars($output);
        $jsonOutput = json_encode($escapedOutput);

        echo '<script>
            (function() {
                let logDiv = document.getElementById("install-log-div");
                let preElement = document.createElement("pre");
                preElement.className = "dark-output";
                preElement.textContent = ' . $jsonOutput . ';
                logDiv.appendChild(preElement);
            })();
        </script>';
        $this->flush();
    }

    /**
     * 显示警告
     */
    public function showWarning(string $message): void
    {
        $escapedMessage = htmlspecialchars($message);

        echo '<script>
            (function() {
                let logDiv = document.getElementById("install-log-div");
                let warningDiv = document.createElement("div");
                warningDiv.className = "warning";
                warningDiv.innerHTML = "⚠ ' . $escapedMessage . '";
                logDiv.appendChild(warningDiv);
            })();
        </script>';
        $this->flush();
    }

    /**
     * 显示错误
     */
    public function showError(string $message): void
    {
        $escapedMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

        echo '<script>
            (function() {
                let logDiv = document.getElementById("install-log-div");
                let errorDiv = document.createElement("div");
                errorDiv.className = "error";
                errorDiv.innerHTML = "✘ 安装失败: ' . $escapedMessage . '";
                logDiv.appendChild(errorDiv);
            })();
        </script>';
        $this->flush();
    }

    /**
     * 刷新输出缓冲
     */
    private function flush(): void
    {
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
}
