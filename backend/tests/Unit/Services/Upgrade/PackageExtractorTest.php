<?php

namespace Tests\Unit\Services\Upgrade;

use App\Services\Upgrade\PackageExtractor;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class PackageExtractorTest extends TestCase
{
    protected PackageExtractor $extractor;

    protected string $testDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extractor = new PackageExtractor;
        $this->testDir = storage_path('upgrades/test_'.uniqid());
        File::makeDirectory($this->testDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // 清理测试目录
        if (File::isDirectory($this->testDir)) {
            File::deleteDirectory($this->testDir);
        }

        parent::tearDown();
    }

    public function test_extract_throws_exception_for_missing_package(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('升级包不存在');

        $this->extractor->extract('/nonexistent/package.zip');
    }

    public function test_extract_throws_exception_for_invalid_zip(): void
    {
        // 创建一个无效的 zip 文件
        $invalidZip = "$this->testDir/invalid.zip";
        File::put($invalidZip, 'not a zip file');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('无法打开升级包');

        $this->extractor->extract($invalidZip);
    }

    public function test_extract_valid_package(): void
    {
        // 创建一个有效的 zip 文件
        $zipPath = $this->createTestPackage();

        $extractedPath = $this->extractor->extract($zipPath);

        $this->assertDirectoryExists($extractedPath);
        $this->assertFileExists("$extractedPath/manifest.json");

        // 清理
        File::deleteDirectory($extractedPath);
    }

    public function test_validate_package_throws_for_missing_manifest(): void
    {
        // 创建没有 manifest.json 的目录
        $packageDir = "$this->testDir/package";
        File::makeDirectory($packageDir, 0755, true);
        File::makeDirectory("$packageDir/backend/app", 0755, true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('缺少 manifest.json');

        $this->extractor->validatePackage($packageDir);
    }

    public function test_validate_package_throws_for_invalid_manifest(): void
    {
        // 创建无效的 manifest.json
        $packageDir = "$this->testDir/package";
        File::makeDirectory($packageDir, 0755, true);
        File::put("$packageDir/manifest.json", 'invalid json');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('manifest.json 格式错误');

        $this->extractor->validatePackage($packageDir);
    }

    public function test_validate_package_throws_for_missing_version(): void
    {
        // 创建缺少 version 的 manifest.json
        $packageDir = "$this->testDir/package";
        File::makeDirectory($packageDir, 0755, true);
        File::put("$packageDir/manifest.json", json_encode(['name' => 'test']));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('缺少版本信息');

        $this->extractor->validatePackage($packageDir);
    }

    public function test_validate_package_success(): void
    {
        $packageDir = $this->createValidPackageDir();

        $result = $this->extractor->validatePackage($packageDir);

        $this->assertTrue($result);
    }

    public function test_detect_web_user_returns_www_for_baota(): void
    {
        $reflection = new \ReflectionClass($this->extractor);
        $method = $reflection->getMethod('detectWebUser');
        $method->setAccessible(true);

        $result = $method->invoke($this->extractor);

        // 当前环境是宝塔，应该返回 www
        if (is_dir('/www/server') || str_starts_with(base_path(), '/www/wwwroot/')) {
            $this->assertEquals('www', $result);
        } else {
            $this->assertEquals('www-data', $result);
        }
    }

    public function test_find_manifest_in_root(): void
    {
        $packageDir = "$this->testDir/package";
        File::makeDirectory($packageDir, 0755, true);
        File::put("$packageDir/manifest.json", '{}');

        $reflection = new \ReflectionClass($this->extractor);
        $method = $reflection->getMethod('findManifest');
        $method->setAccessible(true);

        $result = $method->invoke($this->extractor, $packageDir);

        $this->assertEquals("$packageDir/manifest.json", $result);
    }

    public function test_find_manifest_in_subdirectory(): void
    {
        $packageDir = "$this->testDir/package";
        $subDir = "$packageDir/ssl-manager-1.0.0";
        File::makeDirectory($subDir, 0755, true);
        File::put("$subDir/manifest.json", '{}');

        $reflection = new \ReflectionClass($this->extractor);
        $method = $reflection->getMethod('findManifest');
        $method->setAccessible(true);

        $result = $method->invoke($this->extractor, $packageDir);

        $this->assertEquals("$subDir/manifest.json", $result);
    }

    public function test_find_backend_dir_direct(): void
    {
        $packageDir = "$this->testDir/package";
        File::makeDirectory("$packageDir/backend/app", 0755, true);

        $reflection = new \ReflectionClass($this->extractor);
        $method = $reflection->getMethod('findBackendDir');
        $method->setAccessible(true);

        $result = $method->invoke($this->extractor, $packageDir);

        $this->assertEquals("$packageDir/backend", $result);
    }

    public function test_find_backend_dir_with_app(): void
    {
        $packageDir = "$this->testDir/package";
        File::makeDirectory("$packageDir/app", 0755, true);

        $reflection = new \ReflectionClass($this->extractor);
        $method = $reflection->getMethod('findBackendDir');
        $method->setAccessible(true);

        $result = $method->invoke($this->extractor, $packageDir);

        $this->assertEquals($packageDir, $result);
    }

    public function test_cleanup_removes_extract_directory(): void
    {
        $extractDir = "$this->testDir/extract_test123";
        File::makeDirectory($extractDir, 0755, true);
        File::put("$extractDir/test.txt", 'test');

        $this->extractor->cleanup($extractDir);

        $this->assertDirectoryDoesNotExist($extractDir);
    }

    public function test_cleanup_ignores_non_extract_directory(): void
    {
        $normalDir = "$this->testDir/normal_dir";
        File::makeDirectory($normalDir, 0755, true);

        $this->extractor->cleanup($normalDir);

        // 非 extract_ 开头的目录不应该被删除
        $this->assertDirectoryExists($normalDir);
    }

    public function test_get_download_path(): void
    {
        $path = $this->extractor->getDownloadPath();

        $this->assertNotEmpty($path);
        $this->assertDirectoryExists($path);
    }

    /**
     * 创建测试用的有效升级包 ZIP
     */
    protected function createTestPackage(): string
    {
        $packageDir = "$this->testDir/package_source";
        File::makeDirectory("$packageDir/backend/app", 0755, true);
        File::makeDirectory("$packageDir/backend/config", 0755, true);

        File::put("$packageDir/manifest.json", json_encode([
            'version' => '1.0.0',
            'name' => 'Test Package',
        ]));
        File::put("$packageDir/backend/app/test.php", '<?php // test');
        File::put("$packageDir/backend/config/test.php", '<?php return [];');

        $zipPath = "$this->testDir/test_package.zip";
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);

        $this->addDirToZip($zip, $packageDir, '');

        $zip->close();

        // 清理临时目录
        File::deleteDirectory($packageDir);

        return $zipPath;
    }

    /**
     * 递归添加目录到 ZIP
     */
    protected function addDirToZip(ZipArchive $zip, string $dir, string $prefix): void
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
            $this->addDirToZip($zip, $subDir, $newPrefix);
        }
    }

    /**
     * 创建有效的升级包目录结构
     */
    protected function createValidPackageDir(): string
    {
        $packageDir = "$this->testDir/valid_package";
        File::makeDirectory("$packageDir/backend/app", 0755, true);
        File::makeDirectory("$packageDir/backend/config", 0755, true);

        File::put("$packageDir/manifest.json", json_encode([
            'version' => '1.0.0',
            'name' => 'Test Package',
            'build_time' => date('Y-m-d H:i:s'),
        ]));

        return $packageDir;
    }
}
