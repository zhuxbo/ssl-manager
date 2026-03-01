<?php

use Illuminate\Support\Facades\File;

test('签名为 cache:clear-all', function () {
    $this->artisan('cache:clear-all --quick --without-composer --without-opcache')->assertSuccessful();
});

test('快速模式输出简洁信息', function () {
    $this->artisan('cache:clear-all --quick --without-composer --without-opcache')
        ->expectsOutputToContain('所有缓存清除完成')
        ->assertSuccessful();
});

test('正常模式输出详细信息', function () {
    $this->artisan('cache:clear-all --without-composer --without-opcache')
        ->expectsOutputToContain('开始清除')
        ->expectsOutputToContain('所有缓存清除完成')
        ->assertSuccessful();
});

test('返回成功退出码并清理缓存文件', function () {
    $bootstrapCacheFile = base_path('bootstrap/cache/pest-temp.php');
    $storageCacheFile = base_path('storage/framework/cache/data/pest-temp.cache');
    $storageViewFile = base_path('storage/framework/views/pest-temp.view.php');
    $storageSessionFile = base_path('storage/framework/sessions/pest-temp.session');

    File::ensureDirectoryExists(dirname($bootstrapCacheFile));
    File::ensureDirectoryExists(dirname($storageCacheFile));
    File::ensureDirectoryExists(dirname($storageViewFile));
    File::ensureDirectoryExists(dirname($storageSessionFile));

    File::put($bootstrapCacheFile, '<?php return true;');
    File::put($storageCacheFile, 'cache');
    File::put($storageViewFile, '<html></html>');
    File::put($storageSessionFile, 'session');

    expect(File::exists($bootstrapCacheFile))->toBeTrue();
    expect(File::exists($storageCacheFile))->toBeTrue();
    expect(File::exists($storageViewFile))->toBeTrue();
    expect(File::exists($storageSessionFile))->toBeTrue();

    $this->artisan('cache:clear-all --quick --without-composer --without-opcache')
        ->assertExitCode(0);

    expect(File::exists($bootstrapCacheFile))->toBeFalse();
    expect(File::exists($storageCacheFile))->toBeFalse();
    expect(File::exists($storageViewFile))->toBeFalse();
    expect(File::exists($storageSessionFile))->toBeFalse();
    expect(File::exists(base_path('bootstrap/cache/.gitignore')))->toBeTrue();
    expect(File::exists(base_path('storage/framework/views/.gitignore')))->toBeTrue();
});
