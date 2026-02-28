<?php

test('签名为 cache:clear-all', function () {
    $this->artisan('cache:clear-all --quick')->assertSuccessful();
});

test('快速模式输出简洁信息', function () {
    $this->artisan('cache:clear-all --quick')
        ->expectsOutputToContain('所有缓存清除完成')
        ->assertSuccessful();
});

test('正常模式输出详细信息', function () {
    $this->artisan('cache:clear-all')
        ->expectsOutputToContain('开始清除')
        ->expectsOutputToContain('所有缓存清除完成')
        ->assertSuccessful();
});

test('返回成功退出码', function () {
    $this->artisan('cache:clear-all --quick')
        ->assertExitCode(0);
});
