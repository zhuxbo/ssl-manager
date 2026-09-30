<?php

namespace Plugins\CloudDeploy\Seeders;

use App\Contracts\ProvidesNotificationTemplateDefaults;
use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder implements ProvidesNotificationTemplateDefaults
{
    public function notificationTemplateDefaults(): array
    {
        $content = <<<'BLADE'
@php
    $pending = ($error_code ?? '') === 'poll_pending';
    $reasons = [
        'poll_pending' => '云平台任务已提交，部署结果尚未确认',
        'retries_exhausted' => '重试次数已耗尽，未能确认部署成功',
        'business_error' => '云平台未能完成部署，请查看部署日志',
        'missing_private_key' => '证书缺少私钥，无法部署',
        'unsupported_algorithm' => '当前部署方式不支持该证书算法',
    ];
@endphp
{{ $pending ? '云部署结果待确认' : '云部署失败' }}

云产品：{{ $product_label ?? $product }}
域名：{{ $domain }}
云账号：{{ $access_name }}
{{ $pending ? '当前状态' : '失败原因' }}：{{ $reasons[$error_code] ?? '部署异常，请查看部署日志' }}
@if (! isset($reasons[$error_code]))
错误代码：{{ $error_code }}
@endif

@if ($pending)
任务已提交云平台，部署结果尚未确认。请先查看部署日志及云平台任务状态，确认结果前请勿重复推送。
@else
证书已签发，但部署任务未能确认成功。请登录系统查看部署日志，核实云平台状态并处理原因后，再按需重新部署。
@endif
BLADE;

        return [
            [
                'code' => 'cloud_deploy_failed',
                'name' => '云部署失败',
                'content' => $content,
                'variables' => [
                    'product',
                    'domain',
                    'access_name',
                    'error_code',
                ],
                'example' => null,
                'status' => 1,
            ],
        ];
    }

    public function run(): void
    {
        foreach ($this->notificationTemplateDefaults() as $template) {
            NotificationTemplate::firstOrCreate(
                ['code' => $template['code']],
                [
                    'name' => $template['name'],
                    'content' => $template['content'],
                    'variables' => $template['variables'],
                    'example' => $template['example'] ?? null,
                    'status' => $template['status'] ?? 1,
                ]
            );
        }
    }

    public function clear(): void
    {
        NotificationTemplate::where('code', 'cloud_deploy_failed')->delete();
    }
}
