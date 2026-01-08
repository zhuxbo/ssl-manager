<?php

namespace Install\View;

use Install\DTO\CheckResult;

/**
 * 页面渲染器
 */
class Renderer
{
    private string $assetsDir;

    public function __construct(?string $assetsDir = null)
    {
        $this->assetsDir = $assetsDir ?? dirname(__DIR__);
    }

    /**
     * 加载模板文件
     */
    public function load(string $templateName): string
    {
        $templateFile = $this->assetsDir . '/' . $templateName . '.html';

        if (! file_exists($templateFile)) {
            throw new \Exception("模板文件不存在: $templateName.html");
        }

        return file_get_contents($templateFile);
    }

    /**
     * 渲染模板
     */
    public function render(string $templateName, array $variables = []): string
    {
        $template = $this->load($templateName);

        foreach ($variables as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }

        return $template;
    }

    /**
     * 输出页面头部
     */
    public function header(): void
    {
        echo file_get_contents($this->assetsDir . '/header.html');
    }

    /**
     * 输出页面尾部
     */
    public function footer(): void
    {
        echo file_get_contents($this->assetsDir . '/footer.html');
    }

    /**
     * 生成错误或警告部分 HTML
     */
    public function messageSection(array $messages, string $type = 'error'): string
    {
        if (empty($messages)) {
            return '';
        }

        $title = $type === 'error' ? '错误' : '警告';
        $sectionId = $type === 'error' ? 'error-section' : 'warning-section';
        $icon = $type === 'error' ? '✘' : '⚠';

        $html = '<h2 id="' . $sectionId . '">' . $title . '</h2>';
        $html .= '<div class="log">';

        foreach ($messages as $message) {
            $html .= '<div class="' . $type . '">' . $icon . ' ' . htmlspecialchars($message) . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * 生成单个检查项 HTML
     */
    public function requirementItem(string $name, string $value, string $status): string
    {
        return '<div class="' . $status . '" style="padding: 5px;">'
            . htmlspecialchars($name) . ': ' . htmlspecialchars($value)
            . '</div>';
    }

    /**
     * 生成检查项列表 HTML
     * @param CheckResult[] $items
     */
    public function requirementsList(array $items): string
    {
        $html = '';

        foreach ($items as $item) {
            $html .= $this->requirementItem($item->name, $item->value, $item->status);
        }

        return $html;
    }

    /**
     * 生成总结信息 HTML
     */
    public function summary(bool $allSuccess, bool $hasWarnings = false, string $successText = '', string $warningText = '', string $errorText = ''): string
    {
        if ($allSuccess) {
            if ($hasWarnings) {
                return '<span class="success" style="padding: 5px;">✓ ' . $successText . '</span>'
                    . '<br><span class="warning" style="padding: 5px;">⚠ ' . $warningText . '</span>';
            }

            return '<span class="success" style="padding: 5px;">✓ ' . $successText . '</span>';
        }

        return '<span class="error" style="padding: 5px;">✘ ' . $errorText . '</span>';
    }

    /**
     * 生成系统检查摘要内容
     */
    public function systemCheckSummary(bool $passed, bool $hasWarnings, array $errorItems = []): string
    {
        if ($passed) {
            if ($hasWarnings) {
                return '<div class="requirement success" style="padding: 15px; margin: 10px 0;">'
                    . '✓ 系统环境检查已通过，可以继续安装'
                    . '</div>'
                    . '<div class="requirement warning" style="padding: 15px; margin: 10px 0;">'
                    . '⚠ 存在一些可选项警告，建议查看详情'
                    . '</div>';
            }

            return '<div class="requirement success" style="padding: 15px; margin: 10px 0;">'
                . '✓ 所有系统环境检查已通过，可以继续安装'
                . '</div>';
        }

        $errorList = implode('、', $errorItems);

        return '<div class="requirement error" style="padding: 15px; margin: 10px 0;">'
            . '✘ 系统环境检查未通过：' . $errorList . '<br>'
            . '<small style="margin-top: 8px; display: block;">请点击"展开"查看详细信息并解决问题</small>'
            . '</div>';
    }
}
