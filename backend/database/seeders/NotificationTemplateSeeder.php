<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // 证书签发通知 - 短信版
            [
                'code' => 'cert_issued',
                'name' => '证书签发通知',
                'content' => '您好 {{ $username }}，您的证书 {{ $domain }} 已签发。',
                'variables' => ['order_id', 'mobile'],
                'example' => '您好 test，您的证书 example.com 已签发。',
                'channels' => ['sms'],
            ],
            // 证书签发通知 - 邮件版
            [
                'code' => 'cert_issued',
                'name' => '证书签发通知',
                'content' => $this->getOrderIssuedHtml(),
                'variables' => ['order_id', 'email'],
                'example' => null,
                'channels' => ['mail'],
            ],
            // 证书到期提醒 - 短信版
            [
                'code' => 'cert_expire',
                'name' => '证书到期提醒',
                'content' => '您好 {{ $username }}，您的以下证书即将到期：{{ $certificates }}',
                'variables' => ['user_id', 'mobile'],
                'example' => '您好 test，您的以下证书即将到期：example.com',
                'channels' => ['sms'],
            ],
            // 证书到期提醒 - 邮件版
            [
                'code' => 'cert_expire',
                'name' => '证书到期提醒',
                'content' => $this->getOrderExpireHtml(),
                'variables' => ['user_id', 'email'],
                'example' => null,
                'channels' => ['mail'],
            ],
            // 安全通知
            [
                'code' => 'security',
                'name' => '安全通知',
                'content' => '您好 {{ $username }}，您的账号发生安全变更：{{ $event }}，如非本人操作请及时处理。',
                'variables' => ['username', 'event'],
                'example' => '您好 test，您的密码已修改，如非本人操作请及时处理。',
                'channels' => ['mail', 'sms'],
            ],
            // 用户创建通知
            [
                'code' => 'user_created',
                'name' => '用户创建通知',
                'content' => '您好，我们为您创建了账号，用户名 {{ $username }}，密码 {{ $password }}，登录地址 {{ $site_url }}',
                'variables' => ['username', 'password', 'site_url'],
                'example' => '您好，我们为您创建了账号，用户名 test，密码 123456，登录地址 www.example.com',
                'channels' => ['mail'],
            ],
            // 任务失败告警 - 邮件版
            [
                'code' => 'task_failed',
                'name' => '任务失败告警',
                'content' => $this->getTaskFailedHtml(),
                'variables' => [
                    'task_id',
                    'error_message',
                ],
                'example' => null,
                'channels' => ['mail'],
            ],
            // 任务失败告警 - 短信版
            [
                'code' => 'task_failed',
                'name' => '任务失败告警',
                'content' => '任务 ID {{ $task_id }} 失败：{{ $error_message }}',
                'variables' => [
                    'task_id',
                    'error_message',
                ],
                'example' => null,
                'channels' => ['sms'],
            ],
        ];

        foreach ($templates as $template) {
            // 根据模型的唯一性约束：code + channels（数组）组合需要唯一
            // 查找是否存在相同 code 且 channels 完全相同的记录
            $existing = NotificationTemplate::where('code', $template['code'])
                ->get()
                ->first(function ($item) use ($template) {
                    // 比较 channels 数组是否完全相同（忽略顺序）
                    $existingChannels = collect($item->channels)->sort()->values()->toArray();
                    $newChannels = collect($template['channels'])->sort()->values()->toArray();

                    return $existingChannels === $newChannels;
                });

            if (! $existing) {
                NotificationTemplate::create([
                    'code' => $template['code'],
                    'name' => $template['name'],
                    'content' => $template['content'],
                    'variables' => $template['variables'],
                    'example' => $template['example'] ?? null,
                    'channels' => $template['channels'],
                    'status' => 1,
                ]);
            }
        }
    }

    /**
     * @noinspection CssRedundantUnit
     * @noinspection HtmlDeprecatedTag
     * @noinspection HtmlDeprecatedAttribute
     * @noinspection HtmlUnknownTarget
     * @noinspection XmlDeprecatedElement
     * @noinspection CssReplaceWithShorthandSafely
     */
    private function getOrderIssuedHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SSL 证书已签发</title>
    <style>
        /* 基础重置 */
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        table { border-collapse: collapse !important; }
        body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; background-color: #f4f6f8; }

        /* 移动端与暗黑模式适配 */
        @media screen and (max-width: 600px) {
            .email-container { width: 100% !important; margin: auto !important; }
            .mobile-padding { padding-left: 20px !important; padding-right: 20px !important; }
            /* 移动端上下间距稍微减小一点，避免太空 */
            .wrapper-padding { padding-top: 30px !important; padding-bottom: 30px !important; }
        }
        @media (prefers-color-scheme: dark) {
            body, .outer-wrapper { background-color: #2d2d2d !important; }
            .content-cell { background-color: #1a1a1a !important; color: #e1e1e1 !important; }
            .card-info { background-color: #252525 !important; border: 1px solid #333333 !important; }
            h1, h2, h3, span, div { color: #e1e1e1 !important; }
            .footer-text { color: #888888 !important; }
            .highlight-text { color: #ffffff !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f6f8;">

    <div style="display: none; font-size: 1px; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden; mso-hide: all; font-family: sans-serif;">
        您申请的 {{ $domain }} 证书已成功签发{{ ($has_attachment ?? true) ? '，请查收附件' : '' }}。
    </div>

    <center style="width: 100%; background-color: #f4f6f8;">
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" class="outer-wrapper" style="background-color: #f4f6f8;">
            <tr>
                <td align="center" class="wrapper-padding" style="padding-top: 50px; padding-bottom: 50px; padding-left: 10px; padding-right: 10px;">

                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width: 600px; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); text-align: left;">

                        <tr>
                            <td style="background-color: #10b981; height: 4px; font-size: 0; line-height: 0;">&nbsp;</td>
                        </tr>

                        <tr>
                            <td class="content-cell mobile-padding" style="padding: 40px 40px 30px 40px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">

                                <h1 style="margin: 0 0 20px 0; font-size: 22px; line-height: 30px; color: #333333; font-weight: 700;">
                                    ✅ {{ ($product_type ?? 'ssl') === 'smime' ? 'S/MIME' : (($product_type ?? 'ssl') === 'codesign' ? '代码签名' : 'SSL') }} 证书已成功签发
                                </h1>

                                <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 26px; color: #555555;">
                                    尊敬的 <span class="highlight-text" style="color: #10b981; font-weight: 600;">{{ $username }}</span>，您好：
                                </p>

                                <p style="margin: 0 0 24px 0; font-size: 16px; line-height: 26px; color: #555555;">
                                    您在 <a href="{{ $site_url }}" style="color: #10b981; text-decoration: none; font-weight: 600;">{{ $site_name }}</a> 申请的 SSL 证书审核通过，现已正式签发。
                                </p>

                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 24px;">
                                    <tr>
                                        <td class="card-info" style="background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 20px;">
                                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                                <tr>
                                                    <td style="padding-bottom: 8px; font-size: 14px; color: #888888; font-family: sans-serif;">证书域名</td>
                                                </tr>
                                                <tr>
                                                    <td class="highlight-text" style="padding-bottom: 16px; font-size: 18px; font-weight: 600; color: #333333; font-family: monospace;">{{ $domain }}</td>
                                                </tr>
                                                <tr>
                                                    <td style="padding-bottom: 8px; font-size: 14px; color: #888888; font-family: sans-serif;">产品名称</td>
                                                </tr>
                                                <tr>
                                                    <td class="highlight-text" style="font-size: 16px; color: #333333; font-family: sans-serif;">{{ $product }}</td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>

                                @if($has_attachment ?? true)
                                <div style="background-color: #ecfdf5; border-left: 4px solid #10b981; padding: 15px; border-radius: 0 4px 4px 0; margin-bottom: 24px;">
                                    <p style="margin: 0; font-size: 15px; line-height: 24px; color: #065f46;">
                                        <strong>📎 附件提醒：</strong><br>
                                        证书文件已打包为 ZIP 附件，请下载后解压并安装。
                                    </p>
                                </div>
                                @endif

                                <p style="margin: 0; font-size: 15px; line-height: 24px; color: #666666;">
                                    如果您在安装过程中遇到任何问题，或附件无法下载，请随时登录控制台或联系我们的技术支持。
                                </p>

                            </td>
                        </tr>

                        <tr>
                            <td class="mobile-padding" style="background-color: #fafafa; padding: 20px 40px; text-align: center; border-top: 1px solid #eeeeee;">
                                <p class="footer-text" style="margin: 0; font-size: 13px; line-height: 20px; color: #999999; font-family: sans-serif;">
                                    感谢您选择 <a href="{{ $site_url }}" style="color: #999999; text-decoration: underline;">{{ $site_name }}</a>
                                </p>
                            </td>
                        </tr>
                    </table>

                    </td>
            </tr>
        </table>
    </center>
</body>
</html>
HTML;
    }

    /**
     * @noinspection CssRedundantUnit
     * @noinspection HtmlDeprecatedTag
     * @noinspection HtmlDeprecatedAttribute
     * @noinspection HtmlUnknownTarget
     * @noinspection XmlDeprecatedElement
     * @noinspection CssReplaceWithShorthandSafely
     */
    private function getOrderExpireHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SSL 证书到期提醒</title>
    <style>
        /* 基础重置 */
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        table { border-collapse: collapse !important; }
        body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; background-color: #f4f6f8; }

        /* 移动端适配 */
        @media screen and (max-width: 600px) {
            .email-container { width: 100% !important; margin: auto !important; }
            .mobile-padding { padding-left: 20px !important; padding-right: 20px !important; }
            .wrapper-padding { padding-top: 30px !important; padding-bottom: 30px !important; }
            /* 强制表格在手机端滚动或调整字号 */
            .data-table th, .data-table td { font-size: 12px !important; padding: 10px 5px !important; }
        }

        /* 暗黑模式适配 */
        @media (prefers-color-scheme: dark) {
            body, .outer-wrapper { background-color: #2d2d2d !important; }
            .white-card { background-color: #1f1f1f !important; border: 1px solid #333333 !important; }
            h1, h2, h3, p, span, div { color: #e1e1e1 !important; }
            .footer-text { color: #888888 !important; }
            .highlight-text { color: #f59e0b !important; }
            /* 表格暗黑模式 */
            .data-table th { background-color: #333333 !important; color: #cccccc !important; border-bottom: 1px solid #444 !important; }
            .data-table td { border-bottom: 1px solid #333 !important; color: #e1e1e1 !important; }
            .warning-box { background-color: #332b00 !important; border-left-color: #f59e0b !important; }
            .warning-text { color: #fbbf24 !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f6f8;">

    <div style="display: none; font-size: 1px; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden; mso-hide: all; font-family: sans-serif;">
        您的 SSL 证书即将过期，请尽快处理续费。
    </div>

    <center style="width: 100%; background-color: #f4f6f8;">
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" class="outer-wrapper" style="background-color: #f4f6f8;">
            <tr>
                <td align="center" class="wrapper-padding" style="padding-top: 50px; padding-bottom: 50px; padding-left: 10px; padding-right: 10px;">

                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" class="white-card" style="max-width: 600px; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); text-align: left;">

                        <tr>
                            <td style="background-color: #f59e0b; height: 4px; font-size: 0; line-height: 0;">&nbsp;</td>
                        </tr>

                        <tr>
                            <td class="mobile-padding" style="padding: 40px 40px 30px 40px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">

                                <h1 style="margin: 0 0 20px 0; font-size: 22px; line-height: 30px; color: #333333; font-weight: 700;">
                                    ⚠️ SSL 证书到期提醒
                                </h1>

                                <p style="margin: 0 0 15px 0; font-size: 16px; line-height: 26px; color: #555555;">
                                    尊敬的 <span class="highlight-text" style="color: #f59e0b; font-weight: 600;">{{ $username }}</span>，您好：
                                </p>

                                <p style="margin: 0 0 25px 0; font-size: 15px; line-height: 26px; color: #555555;">
                                    为了不影响网站的正常访问和数据安全，请注意下列证书即将到期，建议您尽快完成续费。
                                </p>

                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" class="data-table" style="margin-bottom: 24px; border-collapse: collapse; width: 100%;">
                                    <thead>
                                        <tr style="background-color: #fffbeb;">
                                            <th align="left" style="padding: 12px 10px; border-bottom: 2px solid #fcd34d; font-size: 13px; font-weight: 600; color: #92400e; text-transform: uppercase;">序号</th>
                                            <th align="left" style="padding: 12px 10px; border-bottom: 2px solid #fcd34d; font-size: 13px; font-weight: 600; color: #92400e; text-transform: uppercase;">域名</th>
                                            <th align="left" style="padding: 12px 10px; border-bottom: 2px solid #fcd34d; font-size: 13px; font-weight: 600; color: #92400e; text-transform: uppercase;">到期时间</th>
                                            <th align="center" style="padding: 12px 10px; border-bottom: 2px solid #fcd34d; font-size: 13px; font-weight: 600; color: #92400e; text-transform: uppercase;">剩余</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {{-- 数据库模板中的 Blade 循环 --}}
                                        @foreach($certificates as $index => $cert)
                                        <tr>
                                            <td align="left" style="padding: 12px 10px; border-bottom: 1px solid #eeeeee; font-size: 14px; color: #666666;">
                                                {{ $index + 1 }}
                                            </td>
                                            <td align="left" style="padding: 12px 10px; border-bottom: 1px solid #eeeeee; font-size: 14px; font-weight: 600; color: #333333; font-family: monospace;">
                                                {{ $cert['domain'] }}
                                            </td>
                                            <td align="left" style="padding: 12px 10px; border-bottom: 1px solid #eeeeee; font-size: 14px; color: #666666;">
                                                {{ $cert['expire_at'] }}
                                            </td>
                                            <td align="center" style="padding: 12px 10px; border-bottom: 1px solid #eeeeee; font-size: 14px;">
                                                @if($cert['days_left'] <= 7)
                                                    <span style="background-color: #fee2e2; color: #dc2626; padding: 2px 6px; border-radius: 4px; font-weight: bold; font-size: 12px;">{{ $cert['days_left'] }}天</span>
                                                @else
                                                    <span style="background-color: #fffbeb; color: #d97706; padding: 2px 6px; border-radius: 4px; font-weight: bold; font-size: 12px;">{{ $cert['days_left'] }}天</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @endforeach
                                        {{-- 循环结束 --}}
                                    </tbody>
                                </table>

                                <div class="warning-box" style="background-color: #fffbeb; border-left: 4px solid #f59e0b; padding: 15px; border-radius: 0 4px 4px 0; margin-bottom: 30px;">
                                    <p class="warning-text" style="margin: 0; font-size: 14px; line-height: 22px; color: #92400e;">
                                        <strong>重要提示：</strong><br>
                                        证书过期后，浏览器将拦截访问并显示“不安全”警告，严重影响用户信任。
                                    </p>
                                </div>

                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                    <tr>
                                        <td align="center">
                                            <a href="{{ $site_url }}" style="background-color:#f59e0b; border-radius:4px; color:#ffffff; display:inline-block; font-family:sans-serif; font-size:16px; font-weight:bold; line-height:44px; text-align:center; text-decoration:none; width:200px; -webkit-text-size-adjust:none;">
                                                立即续费
                                            </a>
                                        </td>
                                    </tr>
                                </table>

                            </td>
                        </tr>

                        <tr>
                            <td class="mobile-padding" style="background-color: #fafafa; padding: 20px 40px; text-align: center; border-top: 1px solid #eeeeee;">
                                <p class="footer-text" style="margin: 0; font-size: 13px; line-height: 20px; color: #999999; font-family: sans-serif;">
                                    感谢您选择 <a href="{{ $site_url }}" style="color: #999999; text-decoration: underline;">{{ $site_name }}</a>
                                </p>
                            </td>
                        </tr>
                    </table>

                    </td>
            </tr>
        </table>
    </center>
</body>
</html>
HTML;
    }

    /**
     * @noinspection CssRedundantUnit
     * @noinspection HtmlDeprecatedTag
     * @noinspection HtmlDeprecatedAttribute
     * @noinspection HtmlUnknownTarget
     * @noinspection XmlDeprecatedElement
     * @noinspection CssReplaceWithShorthandSafely
     * @noinspection CssNonIntegerLengthInPixels
     */
    private function getTaskFailedHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>队列任务执行失败</title>
    <style>
        /* 基础重置 */
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        table { border-collapse: collapse !important; }
        body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; background-color: #f4f6f8; }

        /* 移动端适配 */
        @media screen and (max-width: 600px) {
            .email-container { width: 100% !important; margin: auto !important; }
            .mobile-padding { padding-left: 20px !important; padding-right: 20px !important; }
            .wrapper-padding { padding-top: 30px !important; padding-bottom: 30px !important; }
            /* 手机端表格变为块级显示，标签和值换行 */
            .data-row td { display: block !important; width: 100% !important; padding-left: 0 !important; padding-right: 0 !important; border: none !important; }
            .data-label { padding-bottom: 4px !important; font-size: 12px !important; color: #999 !important; }
            .data-value { padding-bottom: 16px !important; border-bottom: 1px solid #eee !important; }
        }

        /* 暗黑模式适配 */
        @media (prefers-color-scheme: dark) {
            body, .outer-wrapper { background-color: #2d2d2d !important; }
            .white-card { background-color: #1f1f1f !important; border: 1px solid #333333 !important; }
            h1, h2, h3, p, span, div { color: #e1e1e1 !important; }
            .data-label { color: #888888 !important; }
            .data-value { color: #e1e1e1 !important; border-bottom-color: #333 !important; }
            .code-block { background-color: #111 !important; border: 1px solid #333 !important; color: #a5b4fc !important; }
            .error-text { color: #f87171 !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f6f8;">

    <div style="display: none; font-size: 1px; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden; mso-hide: all; font-family: sans-serif;">
        任务执行失败：{{ $task_action }} (订单ID: {{ $order_id }}) - {{ $error_message }}
    </div>

    <center style="width: 100%; background-color: #f4f6f8;">
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" class="outer-wrapper" style="background-color: #f4f6f8;">
            <tr>
                <td align="center" class="wrapper-padding" style="padding-top: 50px; padding-bottom: 50px; padding-left: 10px; padding-right: 10px;">

                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" class="white-card" style="max-width: 680px; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); text-align: left;">

                        <tr>
                            <td style="background-color: #dc2626; height: 4px; font-size: 0; line-height: 0;">&nbsp;</td>
                        </tr>

                        <tr>
                            <td class="mobile-padding" style="padding: 40px 40px 30px 40px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">

                                <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0">
                                    <tr>
                                        <td width="40" style="padding-right: 15px; vertical-align: middle;">
                                            <img src="https://img.icons8.com/fluency/48/cancel.png" width="32" height="32" alt="Error" style="display: block; border: 0;">
                                        </td>
                                        <td style="vertical-align: middle;">
                                            <h1 style="margin: 0; font-size: 20px; line-height: 30px; color: #dc2626; font-weight: 700;">
                                                队列任务执行失败
                                            </h1>
                                        </td>
                                    </tr>
                                </table>

                                <div style="margin-top: 20px; margin-bottom: 25px; height: 1px; background-color: #eeeeee; font-size: 0; line-height: 0;">&nbsp;</div>

                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border-collapse: collapse;">

                                    <tr class="data-row">
                                        <td class="data-label" width="30%" style="padding: 10px 0; font-size: 13px; color: #888888; vertical-align: top; border-bottom: 1px solid #f0f0f0;">订单 ID</td>
                                        <td class="data-value" style="padding: 10px 0; font-size: 14px; color: #333333; font-family: monospace; font-weight: 600; border-bottom: 1px solid #f0f0f0;">
                                            {{ $order_id }}
                                        </td>
                                    </tr>

                                    <tr class="data-row">
                                        <td class="data-label" style="padding: 10px 0; font-size: 13px; color: #888888; vertical-align: top; border-bottom: 1px solid #f0f0f0;">任务记录 ID</td>
                                        <td class="data-value" style="padding: 10px 0; font-size: 14px; color: #333333; font-family: monospace; border-bottom: 1px solid #f0f0f0;">
                                            {{ $task_id }}
                                        </td>
                                    </tr>

                                    <tr class="data-row">
                                        <td class="data-label" style="padding: 10px 0; font-size: 13px; color: #888888; vertical-align: top; border-bottom: 1px solid #f0f0f0;">动作 (Action)</td>
                                        <td class="data-value" style="padding: 10px 0; font-size: 14px; color: #333333; border-bottom: 1px solid #f0f0f0;">
                                            {{ $task_action }}
                                        </td>
                                    </tr>

                                    <tr class="data-row">
                                        <td class="data-label" style="padding: 10px 0; font-size: 13px; color: #888888; vertical-align: top; border-bottom: 1px solid #f0f0f0;">执行状态</td>
                                        <td class="data-value" style="padding: 10px 0; font-size: 14px; color: #333333; border-bottom: 1px solid #f0f0f0;">
                                            <span style="background-color: #fee2e2; color: #991b1b; padding: 2px 6px; border-radius: 4px; font-size: 12px; font-weight: bold;">
                                                {{ $task_status }}
                                            </span>
                                        </td>
                                    </tr>

                                    <tr class="data-row">
                                        <td class="data-label" style="padding: 10px 0; font-size: 13px; color: #888888; vertical-align: top; border-bottom: 1px solid #f0f0f0;">执行次数</td>
                                        <td class="data-value" style="padding: 10px 0; font-size: 14px; color: #333333; border-bottom: 1px solid #f0f0f0;">
                                            {{ $attempts }}
                                        </td>
                                    </tr>

                                    <tr class="data-row">
                                        <td class="data-label" style="padding: 10px 0; font-size: 13px; color: #888888; vertical-align: top; border-bottom: 1px solid #f0f0f0;">错误信息</td>
                                        <td class="data-value error-text" style="padding: 10px 0; font-size: 14px; color: #dc2626; font-weight: 600; border-bottom: 1px solid #f0f0f0;">
                                            {{ $error_message }}
                                        </td>
                                    </tr>

                                    <tr class="data-row">
                                        <td class="data-label" style="padding: 10px 0; font-size: 13px; color: #888888; vertical-align: top; border-bottom: 1px solid #f0f0f0;">时间</td>
                                        <td class="data-value" style="padding: 10px 0; font-size: 13px; color: #555555; border-bottom: 1px solid #f0f0f0;">
                                            创建于: {{ $created_at }}<br>
                                            执行于: {{ $executed_at }}
                                        </td>
                                    </tr>
                                </table>

                                <div style="margin-top: 30px; margin-bottom: 10px;">
                                    <span style="font-size: 14px; font-weight: bold; color: #333333; text-transform: uppercase; letter-spacing: 0.5px;">运行结果详情</span>
                                </div>

                                <div class="code-block" style="background-color: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; padding: 15px; font-size: 13px; line-height: 1.6; color: #333;">
                                    <div style="font-weight: bold; margin-bottom: 5px; color: #555;">Params:</div>
                                    <pre style="margin: 0; white-space: pre-wrap; word-break: break-all; font-family: 'Menlo', 'Consolas', monospace; font-size: 12px; color: #4b5563;">{{ $params }}</pre>
                                </div>

                                <div class="code-block" style="background-color: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; padding: 15px; font-size: 13px; line-height: 1.6; color: #333;">
                                    <div style="font-weight: bold; margin-bottom: 5px; color: #555;">Result:</div>
                                    <pre style="margin: 0; white-space: pre-wrap; word-break: break-all; font-family: 'Menlo', 'Consolas', monospace; font-size: 12px; color: #4b5563;">{{ $result }}</pre>
                                </div>

                            </td>
                        </tr>
                    </table>

                </td>
            </tr>
        </table>
    </center>
</body>
</html>
HTML;
    }
}
