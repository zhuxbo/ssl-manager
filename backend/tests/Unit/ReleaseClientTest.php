<?php

namespace Tests\Unit;

use App\Services\Upgrade\ReleaseClient;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ReleaseClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('upgrade.source.provider', 'github');
        Config::set('upgrade.source.github', [
            'owner' => 'test-owner',
            'repo' => 'test-repo',
            'api_base' => 'https://api.github.com',
        ]);
        Config::set('version.channel', 'main');
    }

    public function test_match_channel_main(): void
    {
        $client = new ReleaseClient;

        $reflection = new \ReflectionClass($client);
        $method = $reflection->getMethod('matchChannel');
        $method->setAccessible(true);

        // main 通道：不带 -dev 后缀
        $this->assertTrue($method->invoke($client, 'v1.0.0', 'main'));
        $this->assertTrue($method->invoke($client, 'v2.1.3', 'main'));
        $this->assertFalse($method->invoke($client, 'v1.0.0-dev', 'main'));
        $this->assertFalse($method->invoke($client, 'v2.0.0-dev', 'main'));
    }

    public function test_match_channel_dev(): void
    {
        $client = new ReleaseClient;

        $reflection = new \ReflectionClass($client);
        $method = $reflection->getMethod('matchChannel');
        $method->setAccessible(true);

        // dev 通道：带 -dev 后缀
        $this->assertTrue($method->invoke($client, 'v1.0.0-dev', 'dev'));
        $this->assertTrue($method->invoke($client, 'v2.1.3-dev', 'dev'));
        $this->assertFalse($method->invoke($client, 'v1.0.0', 'dev'));
        $this->assertFalse($method->invoke($client, 'v2.0.0', 'dev'));
    }

    public function test_normalize_release(): void
    {
        $client = new ReleaseClient;

        $reflection = new \ReflectionClass($client);
        $method = $reflection->getMethod('normalizeRelease');
        $method->setAccessible(true);

        $rawRelease = [
            'tag_name' => 'v1.2.3',
            'name' => 'Release 1.2.3',
            'body' => 'Release notes',
            'prerelease' => false,
            'created_at' => '2025-01-15T10:00:00Z',
            'published_at' => '2025-01-15T10:30:00Z',
            'assets' => [
                [
                    'name' => 'ssl-manager-full-1.2.3.zip',
                    'size' => 1024000,
                    'browser_download_url' => 'https://example.com/full.zip',
                ],
                [
                    'name' => 'ssl-manager-upgrade-1.2.3.zip',
                    'size' => 512000,
                    'browser_download_url' => 'https://example.com/upgrade.zip',
                ],
            ],
        ];

        $result = $method->invoke($client, $rawRelease);

        $this->assertEquals('1.2.3', $result['version']);
        $this->assertEquals('v1.2.3', $result['tag_name']);
        $this->assertEquals('Release 1.2.3', $result['name']);
        $this->assertEquals('Release notes', $result['body']);
        $this->assertFalse($result['prerelease']);
        $this->assertCount(2, $result['assets']);
    }

    public function test_normalize_release_with_v_prefix(): void
    {
        $client = new ReleaseClient;

        $reflection = new \ReflectionClass($client);
        $method = $reflection->getMethod('normalizeRelease');
        $method->setAccessible(true);

        $releases = [
            ['tag_name' => 'v1.0.0', 'expected' => '1.0.0'],
            ['tag_name' => 'V2.0.0', 'expected' => '2.0.0'],
            ['tag_name' => '3.0.0', 'expected' => '3.0.0'],
        ];

        foreach ($releases as $testCase) {
            $result = $method->invoke($client, ['tag_name' => $testCase['tag_name']]);
            $this->assertEquals($testCase['expected'], $result['version']);
        }
    }

    public function test_find_upgrade_package_url(): void
    {
        $client = new ReleaseClient;

        $release = [
            'assets' => [
                ['name' => 'ssl-manager-full-1.0.0.zip', 'browser_download_url' => 'https://example.com/full.zip'],
                ['name' => 'ssl-manager-upgrade-1.0.0.zip', 'browser_download_url' => 'https://example.com/upgrade.zip'],
                ['name' => 'ssl-manager-docker-1.0.0.zip', 'browser_download_url' => 'https://example.com/docker.zip'],
            ],
        ];

        $url = $client->findUpgradePackageUrl($release);

        $this->assertEquals('https://example.com/upgrade.zip', $url);
    }

    public function test_find_upgrade_package_url_not_found(): void
    {
        $client = new ReleaseClient;

        $release = [
            'assets' => [
                ['name' => 'ssl-manager-full-1.0.0.zip', 'browser_download_url' => 'https://example.com/full.zip'],
            ],
        ];

        $url = $client->findUpgradePackageUrl($release);

        $this->assertNull($url);
    }

    public function test_find_full_package_url(): void
    {
        $client = new ReleaseClient;

        $release = [
            'assets' => [
                ['name' => 'ssl-manager-full-1.0.0.zip', 'browser_download_url' => 'https://example.com/full.zip'],
                ['name' => 'ssl-manager-upgrade-1.0.0.zip', 'browser_download_url' => 'https://example.com/upgrade.zip'],
            ],
        ];

        $url = $client->findFullPackageUrl($release);

        $this->assertEquals('https://example.com/full.zip', $url);
    }

    public function test_find_full_package_url_not_found(): void
    {
        $client = new ReleaseClient;

        $release = [
            'assets' => [
                ['name' => 'ssl-manager-upgrade-1.0.0.zip', 'browser_download_url' => 'https://example.com/upgrade.zip'],
            ],
        ];

        $url = $client->findFullPackageUrl($release);

        $this->assertNull($url);
    }

    public function test_find_package_url_empty_assets(): void
    {
        $client = new ReleaseClient;

        $release = ['assets' => []];

        $this->assertNull($client->findUpgradePackageUrl($release));
        $this->assertNull($client->findFullPackageUrl($release));
    }

    public function test_find_package_url_no_assets_key(): void
    {
        $client = new ReleaseClient;

        $release = [];

        $this->assertNull($client->findUpgradePackageUrl($release));
        $this->assertNull($client->findFullPackageUrl($release));
    }
}
