<?php

use App\Services\Upgrade\PackageExtractor;
use Illuminate\Support\Facades\File;

uses(Tests\TestCase::class);

beforeEach(function () {
    $this->extractor = new PackageExtractor;
    $this->testDir = storage_path('upgrades/test_'.uniqid());
    File::makeDirectory($this->testDir, 0755, true);
});

afterEach(function () {
    // 清理测试目录
    if (File::isDirectory($this->testDir)) {
        File::deleteDirectory($this->testDir);
    }
});

test('extract throws exception for missing package', function () {
    $this->extractor->extract('/nonexistent/package.zip');
})->throws(RuntimeException::class, '升级包不存在');

test('extract throws exception for invalid zip', function () {
    // 创建一个无效的 zip 文件
    $invalidZip = "$this->testDir/invalid.zip";
    File::put($invalidZip, 'not a zip file');

    $this->extractor->extract($invalidZip);
})->throws(RuntimeException::class, '无法打开升级包');

test('extract valid package', function () {
    // 创建一个有效的 zip 文件
    $zipPath = createTestPackage($this->testDir);

    $extractedPath = $this->extractor->extract($zipPath);

    expect($extractedPath)->toBeDirectory();
    expect("$extractedPath/manifest.json")->toBeFile();

    // 清理
    File::deleteDirectory($extractedPath);
});

test('validate package throws for missing manifest', function () {
    // 创建没有 manifest.json 的目录
    $packageDir = "$this->testDir/package";
    File::makeDirectory($packageDir, 0755, true);
    File::makeDirectory("$packageDir/backend/app", 0755, true);

    $this->extractor->validatePackage($packageDir);
})->throws(RuntimeException::class, '缺少 manifest.json');

test('validate package throws for invalid manifest', function () {
    // 创建无效的 manifest.json
    $packageDir = "$this->testDir/package";
    File::makeDirectory($packageDir, 0755, true);
    File::put("$packageDir/manifest.json", 'invalid json');

    $this->extractor->validatePackage($packageDir);
})->throws(RuntimeException::class, 'manifest.json 格式错误');

test('validate package throws for missing version', function () {
    // 创建缺少 version 的 manifest.json
    $packageDir = "$this->testDir/package";
    File::makeDirectory($packageDir, 0755, true);
    File::put("$packageDir/manifest.json", json_encode(['name' => 'test']));

    $this->extractor->validatePackage($packageDir);
})->throws(RuntimeException::class, '缺少版本信息');

test('validate package success', function () {
    $packageDir = createValidPackageDir($this->testDir);

    $result = $this->extractor->validatePackage($packageDir);

    expect($result)->toBeTrue();
});

test('detect web user returns www for baota', function () {
    $reflection = new \ReflectionClass($this->extractor);
    $method = $reflection->getMethod('detectWebUser');
    $method->setAccessible(true);

    $result = $method->invoke($this->extractor);

    // 当前环境是宝塔，应该返回 www
    if (is_dir('/www/server') || str_starts_with(base_path(), '/www/wwwroot/')) {
        expect($result)->toBe('www');
    } else {
        expect($result)->toBe('www-data');
    }
});

test('find manifest in root', function () {
    $packageDir = "$this->testDir/package";
    File::makeDirectory($packageDir, 0755, true);
    File::put("$packageDir/manifest.json", '{}');

    $reflection = new \ReflectionClass($this->extractor);
    $method = $reflection->getMethod('findManifest');
    $method->setAccessible(true);

    $result = $method->invoke($this->extractor, $packageDir);

    expect($result)->toBe("$packageDir/manifest.json");
});

test('find manifest in subdirectory', function () {
    $packageDir = "$this->testDir/package";
    $subDir = "$packageDir/ssl-manager-1.0.0";
    File::makeDirectory($subDir, 0755, true);
    File::put("$subDir/manifest.json", '{}');

    $reflection = new \ReflectionClass($this->extractor);
    $method = $reflection->getMethod('findManifest');
    $method->setAccessible(true);

    $result = $method->invoke($this->extractor, $packageDir);

    expect($result)->toBe("$subDir/manifest.json");
});

test('find backend dir direct', function () {
    $packageDir = "$this->testDir/package";
    File::makeDirectory("$packageDir/backend/app", 0755, true);

    $reflection = new \ReflectionClass($this->extractor);
    $method = $reflection->getMethod('findBackendDir');
    $method->setAccessible(true);

    $result = $method->invoke($this->extractor, $packageDir);

    expect($result)->toBe("$packageDir/backend");
});

test('find backend dir with app', function () {
    $packageDir = "$this->testDir/package";
    File::makeDirectory("$packageDir/app", 0755, true);

    $reflection = new \ReflectionClass($this->extractor);
    $method = $reflection->getMethod('findBackendDir');
    $method->setAccessible(true);

    $result = $method->invoke($this->extractor, $packageDir);

    expect($result)->toBe($packageDir);
});

test('cleanup removes extract directory', function () {
    $extractDir = "$this->testDir/extract_test123";
    File::makeDirectory($extractDir, 0755, true);
    File::put("$extractDir/test.txt", 'test');

    $this->extractor->cleanup($extractDir);

    expect($extractDir)->not->toBeDirectory();
});

test('cleanup ignores non extract directory', function () {
    $normalDir = "$this->testDir/normal_dir";
    File::makeDirectory($normalDir, 0755, true);

    $this->extractor->cleanup($normalDir);

    // 非 extract_ 开头的目录不应该被删除
    expect($normalDir)->toBeDirectory();
});

test('get download path', function () {
    $path = $this->extractor->getDownloadPath();

    expect($path)->not->toBeEmpty();
    expect($path)->toBeDirectory();
});

/**
 * 创建测试用的有效升级包 ZIP
 */
function createTestPackage(string $testDir): string
{
    $packageDir = "$testDir/package_source";
    File::makeDirectory("$packageDir/backend/app", 0755, true);
    File::makeDirectory("$packageDir/backend/config", 0755, true);

    File::put("$packageDir/manifest.json", json_encode([
        'version' => '1.0.0',
        'name' => 'Test Package',
    ]));
    File::put("$packageDir/backend/app/test.php", '<?php // test');
    File::put("$packageDir/backend/config/test.php", '<?php return [];');

    $zipPath = "$testDir/test_package.zip";
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE);

    addDirToZipHelper($zip, $packageDir, '');

    $zip->close();

    // 清理临时目录
    File::deleteDirectory($packageDir);

    return $zipPath;
}

/**
 * 递归添加目录到 ZIP
 */
function addDirToZipHelper(ZipArchive $zip, string $dir, string $prefix): void
{
    $files = File::files($dir);
    foreach ($files as $file) {
        $relativePath = $prefix ? "$prefix/{$file->getFilename()}" : $file->getFilename();
        $zip->addFile($file->getRealPath(), $relativePath);
    }

    $dirs = File::directories($dir);
    foreach ($dirs as $subDir) {
        $dirName = basename($subDir);
        $newPrefix = $prefix ? "$prefix/$dirName" : $dirName;
        addDirToZipHelper($zip, $subDir, $newPrefix);
    }
}

/**
 * 创建有效的升级包目录结构
 */
function createValidPackageDir(string $testDir): string
{
    $packageDir = "$testDir/valid_package";
    File::makeDirectory("$packageDir/backend/app", 0755, true);
    File::makeDirectory("$packageDir/backend/config", 0755, true);

    File::put("$packageDir/manifest.json", json_encode([
        'version' => '1.0.0',
        'name' => 'Test Package',
        'build_time' => date('Y-m-d H:i:s'),
    ]));

    return $packageDir;
}
