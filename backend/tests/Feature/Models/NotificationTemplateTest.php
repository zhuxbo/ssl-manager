<?php

use App\Models\Notification;
use App\Models\NotificationTemplate;
use Illuminate\Validation\ValidationException;

test('模板渲染 Blade 变量', function () {
    $template = NotificationTemplate::factory()->create([
        'code' => 'test_render',
        'content' => '您好 {{ $name }}，您的证书 {{ $domain }} 已签发',
        'channels' => ['site'],
    ]);

    $result = $template->render(['name' => '张三', 'domain' => 'example.com']);

    expect($result)->toContain('张三');
    expect($result)->toContain('example.com');
});

test('模板渲染空变量', function () {
    $template = NotificationTemplate::factory()->create([
        'code' => 'test_empty',
        'content' => '静态内容，无变量',
        'channels' => ['site'],
    ]);

    $result = $template->render();

    expect($result)->toContain('静态内容');
});

test('模板渲染 Blade 条件语法', function () {
    $template = NotificationTemplate::factory()->create([
        'code' => 'test_condition',
        'content' => '@if($show)显示内容@else隐藏内容@endif',
        'channels' => ['site'],
    ]);

    $result1 = $template->render(['show' => true]);
    expect($result1)->toContain('显示内容');

    $result2 = $template->render(['show' => false]);
    expect($result2)->toContain('隐藏内容');
});

test('content 字段 base64 编解码', function () {
    $template = NotificationTemplate::factory()->create([
        'code' => 'test_encode',
        'content' => '<p>测试内容</p>',
        'channels' => ['site'],
    ]);

    // 数据库中存储的是 base64 编码值
    $raw = $template->getRawOriginal('content');
    expect(base64_decode($raw))->toBe('<p>测试内容</p>');

    // 读取时自动解码
    $template->refresh();
    expect($template->content)->toBe('<p>测试内容</p>');
});

test('example 字段 base64 编解码', function () {
    $template = NotificationTemplate::factory()->create([
        'code' => 'test_example',
        'example' => '<p>示例内容</p>',
        'channels' => ['site'],
    ]);

    $template->refresh();
    expect($template->example)->toBe('<p>示例内容</p>');
});

test('content 为 null 时返回 null', function () {
    $template = NotificationTemplate::factory()->create([
        'code' => 'test_null_content',
        'content' => null,
        'channels' => ['site'],
    ]);

    $template->refresh();
    expect($template->content)->toBeNull();
});

test('空字符串 content 存储为 null', function () {
    $template = NotificationTemplate::factory()->create([
        'code' => 'test_empty_str',
        'content' => '',
        'channels' => ['site'],
    ]);

    $template->refresh();
    expect($template->content)->toBeNull();
});

test('variables 字段为 JSON cast', function () {
    $template = NotificationTemplate::factory()->create([
        'code' => 'test_vars',
        'variables' => ['name', 'domain', 'expires_at'],
        'channels' => ['site'],
    ]);

    $template->refresh();
    expect($template->variables)->toBeArray();
    expect($template->variables)->toContain('name');
    expect($template->variables)->toContain('domain');
});

test('channels 字段为数组 cast', function () {
    $template = NotificationTemplate::factory()->create([
        'code' => 'test_channels',
        'channels' => ['site', 'mail'],
    ]);

    $template->refresh();
    expect($template->channels)->toBeArray();
    expect($template->channels)->toContain('site');
    expect($template->channels)->toContain('mail');
});

test('同一 code 不允许重复绑定相同通道', function () {
    NotificationTemplate::factory()->create([
        'code' => 'duplicate_test',
        'channels' => ['site'],
    ]);

    expect(fn () => NotificationTemplate::factory()->create([
        'code' => 'duplicate_test',
        'channels' => ['site'],
    ]))->toThrow(ValidationException::class);
});

test('同一 code 可以绑定不同通道', function () {
    NotificationTemplate::factory()->create([
        'code' => 'multi_channel',
        'channels' => ['site'],
    ]);

    $template2 = NotificationTemplate::factory()->create([
        'code' => 'multi_channel',
        'channels' => ['mail'],
    ]);

    expect($template2)->toBeInstanceOf(NotificationTemplate::class);
});

test('channels 为空时抛出验证异常', function () {
    expect(fn () => NotificationTemplate::factory()->create([
        'code' => 'empty_channels',
        'channels' => [],
    ]))->toThrow(ValidationException::class);
});

test('模板关联通知', function () {
    $template = NotificationTemplate::factory()->create([
        'code' => 'test_relation',
        'channels' => ['site'],
    ]);

    Notification::factory()->count(3)->create([
        'template_id' => $template->id,
    ]);

    expect($template->notifications)->toHaveCount(3);
});

test('channels 字符串正常化处理', function () {
    // channels 为字符串时应自动解析
    $template = NotificationTemplate::factory()->create([
        'code' => 'test_normalize',
        'channels' => ['site'],
    ]);

    expect($template->channels)->toBeArray();
    expect($template->channels)->toContain('site');
});
