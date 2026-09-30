<?php

use App\Models\NotificationTemplate;
use Database\Seeders\NotificationTemplateSeeder;
use Tests\TestCase;

uses(TestCase::class);

function localizedNotificationTemplate(string $code): NotificationTemplate
{
    $defaults = (new NotificationTemplateSeeder)->notificationTemplateDefaults();

    return new NotificationTemplate(collect($defaults)->firstWhere('code', $code));
}

test('部署告警模板将历史和当前统计字段显示为中文并保留值与转义', function () {
    $rendered = localizedNotificationTemplate('system_alert')->render([
        'title' => '自动部署失败汇总',
        'category' => 'deploy_failure',
        'message' => '<script>alert(1)</script>',
        'details' => [
            'report_count' => 3,
            'order_count' => 2,
            'client_failure_count' => 1,
            'server_failure_count' => 2,
            'order_sample' => '75357075760320',
            'window_start' => '2026-09-30 00:00:00',
            'window_end' => '2026-10-01 00:00:00',
            'batch' => 1,
            'batch_count' => 1,
            '订单_75357075760320' => '失败 3 条',
            'custom_field' => '<img src=x>',
        ],
    ]);

    expect($rendered)
        ->toContain('自动部署或签发失败', '本封失败记录数', '本封涉及订单数', '本封客户端上报失败记录数', '本封服务端记录失败数', '待处理订单编号（部分）')
        ->toContain('统计开始时间', '统计结束时间', '当前批次', '总批次数', '75357075760320', '失败 3 条')
        ->toContain('custom_field', '&lt;img src=x&gt;', '&lt;script&gt;')
        ->not->toContain('deploy_failure', 'report_count', 'order_sample', 'window_start', '<script>', '<img src=x>');
});

test('任务失败模板中文展示动作状态且保留原始诊断数据', function () {
    $rendered = localizedNotificationTemplate('task_failed')->render([
        'task_action' => 'commit_acme',
        'task_status' => 'failed',
        'order_id' => 123,
        'task_id' => 456,
        'attempts' => 3,
        'error_message' => '上游请求失败',
        'created_at' => '2026-09-30 00:00:00',
        'executed_at' => '2026-09-30 01:00:00',
        'params' => '{"order_id":123}',
        'result' => '{"code":0}',
    ]);

    expect($rendered)
        ->toContain('提交 ACME 订阅', '失败', '订单编号', '任务编号', '任务动作', '请求参数：', '执行结果：')
        ->toContain('&quot;order_id&quot;:123', '&quot;code&quot;:0')
        ->not->toContain('commit_acme', 'failed', 'Params:', 'Result:', '(Action)');
});

test('告警模板翻译已知枚举但不隐藏未知扩展字段', function () {
    $rendered = localizedNotificationTemplate('system_alert')->render([
        'title' => '检查结果',
        'category' => 'chain_verify',
        'message' => '请检查',
        'details' => [
            'reason' => 'chain_verify_failed',
            'probe' => 'get-products',
            'task_action' => 'sync',
            'custom_value' => '[filtered:non-scalar]',
            '_truncated' => '[truncated:3 keys]',
        ],
    ]);

    expect($rendered)->toContain('证书链校验', '原因', '证书链签名校验失败', '探测方式', '获取产品列表', '同步', '复杂数据已过滤', '已省略 3 个字段');
});

test('资金审计模板中文展示校验层级并保留原始审计记录', function () {
    $rendered = localizedNotificationTemplate('finance_audit')->render([
        'violation_count' => 1,
        'detected_at' => '2026-09-30 00:00:00',
        'violations' => [
            ['layer' => 'L1', 'message' => '账目不一致', 'rows' => [['balance' => '12.34']]],
        ],
    ]);

    expect($rendered)->toContain('账目恒等校验', '资金记录、交易流水与用户余额', 'balance', '12.34');
});

test('续期失败不承诺无条件重试且取消通知不假定原证书仍有效', function () {
    $failed = localizedNotificationTemplate('auto_renew_failed')->render([
        'common_name' => 'example.com', 'action' => 'renew', 'reason' => '产品已下线', 'site_url' => '/',
    ]);
    expect($failed)->toContain('仅仍满足自动续期条件的订单', '重试次数已耗尽或产品已下线')
        ->not->toContain('处理后系统会在后续检测窗口自动重试');
    $cancelled = localizedNotificationTemplate('cert_renew_cancelled')->render([
        'username' => '用户', 'order_id' => 123, 'common_name' => 'example.com',
        'expires_at' => '2020-01-01', 'site_name' => '站点', 'site_url' => '/',
    ]);
    expect($cancelled)->toContain('2020-01-01', '重新申请证书或联系技术支持')
        ->not->toContain('原证书目前仍在有效期内', '手动重新发起续期');
});

test('运维详情明确阈值和时间口径并翻译支付证书名称', function () {
    $rendered = localizedNotificationTemplate('system_alert')->render([
        'title' => '运维提醒', 'category' => 'payment_health', 'message' => '请检查',
        'details' => ['stale_seconds' => 3600, 'period' => 12, 'sample_order_id' => 123, 'alipay_invalid' => 'rootCert(解析失败)'],
    ]);
    expect($rendered)->toContain('超时判定阈值（秒）', '订单服务周期（月）', '触发告警的订单编号', '根证书(解析失败)')
        ->not->toContain('示例订单', 'rootCert', '停滞时长');
});
