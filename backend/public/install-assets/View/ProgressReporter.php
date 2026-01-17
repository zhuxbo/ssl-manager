<?php

namespace Install\View;

/**
 * 安装进度报告器
 * 支持两种模式：
 * 1. HTML 模式（传统表单提交）- 输出 script 标签
 * 2. Stream 模式（AJAX 请求）- 输出 JSON 行
 */
class ProgressReporter
{
    private int $totalSteps;

    private bool $streamMode = false;

    private array $steps = [];

    public function __construct(int $totalSteps = 8)
    {
        $this->totalSteps = $totalSteps;
    }

    /**
     * 设置流模式（用于 AJAX 请求）
     */
    public function setStreamMode(bool $enabled): void
    {
        $this->streamMode = $enabled;

        if ($enabled) {
            // 设置流式响应头
            header('Content-Type: text/plain; charset=utf-8');
            header('Cache-Control: no-cache');
            header('X-Accel-Buffering: no'); // 禁用 nginx 缓冲

            // 禁用所有输出缓冲
            while (ob_get_level() > 0) {
                ob_end_flush();
            }

            // 设置隐式刷新
            ob_implicit_flush(true);
        }
    }

    /**
     * 初始化日志区域
     */
    public function init(): void
    {
        if ($this->streamMode) {
            // 流模式不需要初始化
            return;
        }

        echo '<script>
            (function() {
                var logDiv = document.getElementById("install-log-div");
                while (logDiv.firstChild) {
                    logDiv.removeChild(logDiv.firstChild);
                }
            })();
        </script>';
        $this->flush();
    }

    /**
     * 开始一个步骤
     */
    public function startStep(int $step, string $title): void
    {
        if ($this->streamMode) {
            $this->sendEvent([
                'type' => 'start',
                'step' => $step,
                'total' => $this->totalSteps,
                'message' => $title,
            ]);

            return;
        }

        $escapedTitle = htmlspecialchars($title);

        echo '<script>
            (function() {
                var logDiv = document.getElementById("install-log-div");
                var titleDiv = document.createElement("div");
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
        $this->steps[] = $message;

        if ($this->streamMode) {
            $this->sendEvent([
                'type' => 'complete',
                'message' => $message,
            ]);

            return;
        }

        $escapedMessage = htmlspecialchars($message);

        echo '<script>
            (function() {
                var logDiv = document.getElementById("install-log-div");
                var successDiv = document.createElement("div");
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
        if ($this->streamMode) {
            $this->sendEvent([
                'type' => 'output',
                'message' => $output,
            ]);

            return;
        }

        $escapedOutput = htmlspecialchars($output);
        $jsonOutput = json_encode($escapedOutput);

        echo '<script>
            (function() {
                var logDiv = document.getElementById("install-log-div");
                var preElement = document.createElement("pre");
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
        if ($this->streamMode) {
            $this->sendEvent([
                'type' => 'warning',
                'message' => $message,
            ]);

            return;
        }

        $escapedMessage = htmlspecialchars($message);

        echo '<script>
            (function() {
                var logDiv = document.getElementById("install-log-div");
                var warningDiv = document.createElement("div");
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
        if ($this->streamMode) {
            $this->sendEvent([
                'type' => 'error',
                'message' => '安装失败: ' . $message,
            ]);

            return;
        }

        $escapedMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

        echo '<script>
            (function() {
                var logDiv = document.getElementById("install-log-div");
                var errorDiv = document.createElement("div");
                errorDiv.className = "error";
                errorDiv.innerHTML = "✘ 安装失败: ' . $escapedMessage . '";
                logDiv.appendChild(errorDiv);
            })();
        </script>';
        $this->flush();
    }

    /**
     * 发送成功事件（仅流模式）
     */
    public function sendSuccess(array $steps): void
    {
        if ($this->streamMode) {
            $this->sendEvent([
                'type' => 'success',
                'steps' => $steps,
            ]);
        }
    }

    /**
     * 发送 JSON 事件
     */
    private function sendEvent(array $data): void
    {
        echo json_encode($data, JSON_UNESCAPED_UNICODE) . "\n";
        $this->flush();

        // 添加小延迟确保浏览器能接收到
        usleep(10000); // 10ms
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

    /**
     * 获取已完成的步骤
     */
    public function getSteps(): array
    {
        return $this->steps;
    }
}
