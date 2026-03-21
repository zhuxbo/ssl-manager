<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Plugins\Easy\Controllers\EasyController;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('EasyController 按 sourceLevel 中的 source 键解析等级', function () {
    $controller = new class extends EasyController
    {
        public function exposeResolveLevelFromSource(string $source, array $sourceLevel): string
        {
            return $this->resolveLevelFromSource($source, $sourceLevel);
        }
    };

    $level = $controller->exposeResolveLevelFromSource('taobao', [
        'taobao' => 'gold',
        'wechat' => 'partner',
    ]);

    expect($level)->toBe('gold');
});

test('EasyController sourceLevel 未配置或为空时默认 platinum', function () {
    $controller = new class extends EasyController
    {
        public function exposeResolveLevelFromSource(string $source, array $sourceLevel): string
        {
            return $this->resolveLevelFromSource($source, $sourceLevel);
        }
    };

    expect($controller->exposeResolveLevelFromSource('unknown', []))->toBe('platinum');
    expect($controller->exposeResolveLevelFromSource('taobao', ['taobao' => '']))->toBe('platinum');
});
