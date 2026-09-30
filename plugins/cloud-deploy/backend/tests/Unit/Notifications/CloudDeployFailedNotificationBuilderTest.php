<?php

use App\Models\NotificationTemplate;
use App\Models\User;
use App\Services\Notification\DTOs\NotificationIntent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Plugins\CloudDeploy\Notifications\CloudDeployFailedNotificationBuilder;
use Plugins\CloudDeploy\Seeders\NotificationTemplateSeeder;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('Builder 白名单：误塞的 error/AK 不进 payload，保留白名单字段 + is_html=false', function () {
    $user = User::factory()->create();
    $intent = new NotificationIntent('cloud_deploy_failed', 'user', $user->id, [
        'product' => 'cdn',
        'product_label' => '内容分发网络',
        'domain' => 'a.example.com',
        'access_name' => '我的阿里云',
        'error_code' => 'retries_exhausted',
        // 恶意/误塞：绝不该进 notifications.data
        'error' => 'AccessKeyId=AKIDEXAMPLE Signature=wJalrXUt',
        'last_error' => 'AKIDEXAMPLE',
    ]);

    $payload = (new CloudDeployFailedNotificationBuilder)->build($intent, $user);

    $json = json_encode($payload->data, JSON_UNESCAPED_UNICODE);
    expect($payload->data['product'])->toBe('cdn')
        ->and($payload->data['domain'])->toBe('a.example.com')
        ->and($payload->data['access_name'])->toBe('我的阿里云')
        ->and($payload->data['error_code'])->toBe('retries_exhausted')
        ->and($payload->data['_meta']['is_html'])->toBeFalse()
        ->and($json)->not->toContain('AKIDEXAMPLE')   // AK 被白名单挡掉
        ->and($json)->not->toContain('Signature=');
    expect(array_keys($payload->data))->not->toContain('error');
    expect(array_keys($payload->data))->not->toContain('last_error');
});

test('Seeder 种入 cloud_deploy_failed 模板：启用且声明 Builder 提供的四个变量', function () {
    app(NotificationTemplateSeeder::class)->run();

    $row = DB::table('notification_templates')->where('code', 'cloud_deploy_failed')->first();
    expect($row)->not->toBeNull();
    expect((int) $row->status)->toBe(1);
    expect(base64_decode($row->content))->toContain('$product_label ?? $product')->toContain('{{ $domain }}');
    $variables = [
        'product',
        'domain',
        'access_name',
        'error_code',
    ];
    expect(json_decode((string) $row->variables, true))->toBe($variables);
    $template = NotificationTemplate::query()->where('code', 'cloud_deploy_failed')->firstOrFail();
    expect($template->variables)->toBe($variables);
    expect($template->render([
        'product' => 'cdn',
        'product_label' => '内容分发网络',
        'domain' => 'a.example.com',
        'access_name' => '测试云账号',
        'error_code' => 'retries_exhausted',
    ]))->toContain('云产品：内容分发网络')
        ->toContain('域名：a.example.com')
        ->toContain('云账号：测试云账号')
        ->toContain('失败原因：重试次数已耗尽，未能确认部署成功');
});

test('云部署待确认通知不宣称失败或建议立即重推', function () {
    $user = User::factory()->create();
    $payload = (new CloudDeployFailedNotificationBuilder)->build(new NotificationIntent(
        'cloud_deploy_failed', 'user', $user->id,
        ['product' => 'cdn', 'product_label' => '内容分发网络', 'domain' => 'a.example.com', 'access_name' => '云账号', 'error_code' => 'poll_pending']
    ), $user);
    $template = new NotificationTemplate((new NotificationTemplateSeeder)->notificationTemplateDefaults()[0]);
    $rendered = $template->render($payload->data);
    expect($payload->data['subject'])->toContain('云部署结果待确认')->not->toContain('失败');
    expect($rendered)->toContain('部署结果尚未确认', '确认结果前请勿重复推送', '云产品：内容分发网络')
        ->not->toContain('云部署失败', '手动重推', 'poll_pending');
});
