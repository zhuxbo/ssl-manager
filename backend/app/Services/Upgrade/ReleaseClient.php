<?php

namespace App\Services\Upgrade;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ReleaseClient
{
    protected string $provider;

    protected array $config;

    public function __construct()
    {
        $this->provider = Config::get('upgrade.source.provider', 'gitee');
        $this->config = Config::get("upgrade.source.$this->provider", []);
    }

    /**
     * 获取下载基础 URL
     */
    protected function getDownloadBaseUrl(string $provider): string
    {
        // local provider 直接使用 base_url
        if ($provider === 'local') {
            return Config::get('upgrade.source.local.base_url', '');
        }

        $config = Config::get("upgrade.source.$provider", []);
        $downloadBase = $config['download_base'] ?? '';
        $owner = $config['owner'] ?? '';
        $repo = $config['repo'] ?? '';

        if ($downloadBase && $owner && $repo) {
            return "$downloadBase/$owner/$repo/releases/download";
        }

        return '';
    }

    /**
     * 获取最新 Release
     */
    public function getLatestRelease(?string $channel = null): ?array
    {
        $channel = $channel ?? Config::get('version.channel', 'main');

        try {
            return match ($this->provider) {
                'local' => $this->getLatestLocalRelease($channel),
                'gitee' => $this->getLatestGiteeRelease($channel),
                default => $this->getLatestGithubRelease($channel),
            };
        } catch (\Exception $e) {
            Log::error("获取最新 Release 失败: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * 获取指定版本的 Release
     */
    public function getReleaseByTag(string $tag): ?array
    {
        try {
            return match ($this->provider) {
                'local' => $this->getLocalReleaseByTag($tag),
                'gitee' => $this->getGiteeReleaseByTag($tag),
                default => $this->getGithubReleaseByTag($tag),
            };
        } catch (\Exception $e) {
            Log::error("获取 Release $tag 失败: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * 获取历史版本列表
     */
    public function getReleaseHistory(int $limit = 10, ?string $channel = null): array
    {
        $channel = $channel ?? Config::get('version.channel', 'main');

        try {
            return match ($this->provider) {
                'local' => $this->getLocalReleaseHistory($limit, $channel),
                'gitee' => $this->getGiteeReleaseHistory($limit, $channel),
                default => $this->getGithubReleaseHistory($limit, $channel),
            };
        } catch (\Exception $e) {
            Log::error("获取 Release 历史失败: {$e->getMessage()}");

            return [];
        }
    }

    /**
     * 下载升级包
     */
    public function downloadPackage(string $url, string $savePath): bool
    {
        $timeout = Config::get('upgrade.package.download_timeout', 300);

        // 优先使用 curl 命令（解决 Gitee 403 问题）
        if ($this->downloadWithCurl($url, $savePath, $timeout)) {
            return true;
        }

        // 回退到 PHP HTTP 客户端
        try {
            $request = Http::timeout($timeout)
                ->withOptions(['sink' => $savePath]);

            // 私有仓库需要认证（Gitee 使用 Authorization header）
            if ($this->provider === 'gitee' && ! empty($this->config['access_token'])) {
                $request = $request->withHeaders([
                    'Authorization' => "token {$this->config['access_token']}",
                ]);
            }

            $response = $request->get($url);

            if ($response->successful() && file_exists($savePath)) {
                return true;
            }

            Log::error("下载升级包失败: HTTP {$response->status()}");

            return false;
        } catch (\Exception $e) {
            Log::error("下载升级包失败: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * 下载升级包（多源回退，优先 Gitee）
     *
     * @param  string  $filename  文件名，如 ssl-manager-upgrade-1.0.0.zip
     * @param  string  $tag  版本标签，如 v1.0.0
     * @param  string  $savePath  保存路径
     */
    public function downloadPackageWithFallback(string $filename, string $tag, string $savePath): bool
    {
        // 如果是 local provider，只尝试 local（用于发布前测试）
        if ($this->provider === 'local') {
            $providers = ['local'];
        } else {
            // 下载顺序：Gitee -> GitHub（使用配置）
            $providers = ['gitee', 'github'];
        }

        foreach ($providers as $provider) {
            $downloadBase = $this->getDownloadBaseUrl($provider);
            if (empty($downloadBase)) {
                continue;
            }

            // local provider URL 格式不同：base_url/tag/filename
            $url = "$downloadBase/$tag/$filename";
            Log::info("尝试从 $provider 下载: $url");

            if ($this->downloadPackage($url, $savePath)) {
                Log::info("从 $provider 下载成功");

                return true;
            }

            Log::warning("从 $provider 下载失败，尝试下一个源");
            // 清理可能的部分下载文件
            if (file_exists($savePath)) {
                @unlink($savePath);
            }
        }

        Log::error("所有下载源都失败: $filename");

        return false;
    }

    /**
     * 根据 Release 下载升级包（多源回退）
     */
    public function downloadUpgradePackage(array $release, string $savePath): bool
    {
        $version = $release['version'] ?? '';
        $tagName = $release['tag_name'] ?? "v$version";

        // 尝试从 assets 中获取文件名
        $filename = null;
        foreach ($release['assets'] ?? [] as $asset) {
            $name = $asset['name'] ?? '';
            if (str_contains($name, 'upgrade') && str_ends_with($name, '.zip')) {
                $filename = $name;
                break;
            }
        }

        // 如果没有找到 assets，构造标准文件名
        if (! $filename) {
            $filename = "ssl-manager-upgrade-$version.zip";
        }

        return $this->downloadPackageWithFallback($filename, $tagName, $savePath);
    }

    /**
     * 根据 Release 下载完整包（多源回退）
     */
    public function downloadFullPackage(array $release, string $savePath): bool
    {
        $version = $release['version'] ?? '';
        $tagName = $release['tag_name'] ?? "v$version";

        // 尝试从 assets 中获取文件名
        $filename = null;
        foreach ($release['assets'] ?? [] as $asset) {
            $name = $asset['name'] ?? '';
            if (str_contains($name, 'full') && str_ends_with($name, '.zip')) {
                $filename = $name;
                break;
            }
        }

        // 如果没有找到 assets，构造标准文件名
        if (! $filename) {
            $filename = "ssl-manager-full-$version.zip";
        }

        return $this->downloadPackageWithFallback($filename, $tagName, $savePath);
    }

    /**
     * 使用 curl 命令下载（解决某些环境下 PHP HTTP 客户端的兼容性问题）
     */
    protected function downloadWithCurl(string $url, string $savePath, int $timeout): bool
    {
        // 检查 curl 是否可用
        $curlPath = trim(shell_exec('which curl 2>/dev/null') ?? '');
        if (empty($curlPath)) {
            return false;
        }

        // 构建命令参数数组（安全转义）
        $args = [
            escapeshellarg($curlPath),
            '-sL',
            '--max-time',
            escapeshellarg((string) $timeout),
            '-o',
            escapeshellarg($savePath),
        ];

        // Gitee 私有仓库需要 Authorization header
        if ($this->provider === 'gitee' && ! empty($this->config['access_token'])) {
            $args[] = '-H';
            $args[] = escapeshellarg('Authorization: token ' . $this->config['access_token']);
        }

        $args[] = escapeshellarg($url);
        $args[] = '2>&1';

        $command = implode(' ', $args);

        exec($command, $output, $exitCode);

        if ($exitCode === 0 && file_exists($savePath) && filesize($savePath) > 0) {
            Log::info("使用 curl 下载成功: $url");

            return true;
        }

        Log::warning("curl 下载失败 (exit: $exitCode): " . implode("\n", $output));

        return false;
    }

    /**
     * 从 Release 中查找升级包下载地址
     */
    public function findUpgradePackageUrl(array $release): ?string
    {
        $assets = $release['assets'] ?? [];

        foreach ($assets as $asset) {
            $name = $asset['name'] ?? '';
            // 查找升级包（包含 upgrade 关键字）
            if (str_contains($name, 'upgrade') && str_ends_with($name, '.zip')) {
                return $asset['browser_download_url'] ?? null;
            }
        }

        return null;
    }

    /**
     * 从 Release 中查找完整包下载地址
     */
    public function findFullPackageUrl(array $release): ?string
    {
        $assets = $release['assets'] ?? [];

        foreach ($assets as $asset) {
            $name = $asset['name'] ?? '';
            // 查找完整包（包含 full 关键字）
            if (str_contains($name, 'full') && str_ends_with($name, '.zip')) {
                return $asset['browser_download_url'] ?? null;
            }
        }

        return null;
    }

    /**
     * 获取 Gitee 最新 Release
     */
    protected function getLatestGiteeRelease(string $channel): ?array
    {
        $releases = $this->fetchGiteeReleases();

        // 根据通道过滤并找到最高版本
        $latestRelease = null;
        $latestVersion = '0.0.0';

        foreach ($releases as $release) {
            $tagName = $release['tag_name'] ?? '';
            if ($this->matchChannel($tagName, $channel)) {
                $version = ltrim($tagName, 'vV');
                // 移除预发布后缀进行比较
                $compareVersion = $this->stripPreReleaseSuffix($version);
                $compareLatest = $this->stripPreReleaseSuffix($latestVersion);

                if (version_compare($compareVersion, $compareLatest, '>')) {
                    $latestVersion = $version;
                    $latestRelease = $release;
                }
            }
        }

        return $latestRelease ? $this->normalizeRelease($latestRelease) : null;
    }

    /**
     * 获取 Gitee 指定版本 Release
     */
    protected function getGiteeReleaseByTag(string $tag): ?array
    {
        $url = "{$this->config['api_base']}/repos/{$this->config['owner']}/{$this->config['repo']}/releases/tags/$tag";

        $params = [];
        if (! empty($this->config['access_token'])) {
            $params['access_token'] = $this->config['access_token'];
        }

        $response = Http::get($url, $params);

        if ($response->successful()) {
            return $this->normalizeRelease($response->json());
        }

        return null;
    }

    /**
     * 获取 Gitee Release 历史
     */
    protected function getGiteeReleaseHistory(int $limit, string $channel): array
    {
        $releases = $this->fetchGiteeReleases($limit * 2);
        $filtered = [];

        foreach ($releases as $release) {
            $tagName = $release['tag_name'] ?? '';
            if ($this->matchChannel($tagName, $channel)) {
                $filtered[] = $this->normalizeRelease($release);
                if (count($filtered) >= $limit) {
                    break;
                }
            }
        }

        return $filtered;
    }

    /**
     * 获取 Gitee 所有 Release
     */
    protected function fetchGiteeReleases(int $perPage = 20): array
    {
        $url = "{$this->config['api_base']}/repos/{$this->config['owner']}/{$this->config['repo']}/releases";

        $params = ['per_page' => $perPage];

        // 私有仓库需要 access_token
        if (! empty($this->config['access_token'])) {
            $params['access_token'] = $this->config['access_token'];
        }

        $response = Http::get($url, $params);

        if ($response->successful()) {
            return $response->json() ?? [];
        }

        return [];
    }

    /**
     * 获取 GitHub 最新 Release
     */
    protected function getLatestGithubRelease(string $channel): ?array
    {
        // 遍历所有 releases，找到匹配通道的最高版本（与 Gitee 逻辑一致）
        // 不使用 /releases/latest API，避免获取到 latest tag
        $releases = $this->fetchGithubReleases();
        $latestRelease = null;
        $latestVersion = '0.0.0';

        foreach ($releases as $release) {
            $tagName = $release['tag_name'] ?? '';
            if ($this->matchChannel($tagName, $channel)) {
                $version = ltrim($tagName, 'vV');
                $compareVersion = $this->stripPreReleaseSuffix($version);
                $compareLatest = $this->stripPreReleaseSuffix($latestVersion);

                if (version_compare($compareVersion, $compareLatest, '>')) {
                    $latestVersion = $version;
                    $latestRelease = $release;
                }
            }
        }

        return $latestRelease ? $this->normalizeRelease($latestRelease) : null;
    }

    /**
     * 获取 GitHub 指定版本 Release
     */
    protected function getGithubReleaseByTag(string $tag): ?array
    {
        $url = "{$this->config['api_base']}/repos/{$this->config['owner']}/{$this->config['repo']}/releases/tags/$tag";

        $response = Http::withHeaders(['Accept' => 'application/vnd.github.v3+json'])->get($url);

        if ($response->successful()) {
            return $this->normalizeRelease($response->json());
        }

        return null;
    }

    /**
     * 获取 GitHub Release 历史
     */
    protected function getGithubReleaseHistory(int $limit, string $channel): array
    {
        $releases = $this->fetchGithubReleases($limit * 2);
        $filtered = [];

        foreach ($releases as $release) {
            $tagName = $release['tag_name'] ?? '';
            if ($this->matchChannel($tagName, $channel)) {
                $filtered[] = $this->normalizeRelease($release);
                if (count($filtered) >= $limit) {
                    break;
                }
            }
        }

        return $filtered;
    }

    /**
     * 获取 GitHub 所有 Release
     */
    protected function fetchGithubReleases(int $perPage = 20): array
    {
        $url = "{$this->config['api_base']}/repos/{$this->config['owner']}/{$this->config['repo']}/releases";

        $response = Http::withHeaders(['Accept' => 'application/vnd.github.v3+json'])
            ->get($url, ['per_page' => $perPage]);

        if ($response->successful()) {
            return $response->json() ?? [];
        }

        return [];
    }

    /**
     * 检查 tag 是否匹配通道
     */
    protected function matchChannel(string $tagName, string $channel): bool
    {
        // 过滤 latest tag（latest 只用于安装，不用于在线升级）
        if (strtolower($tagName) === 'latest') {
            return false;
        }

        // main 通道: v1.0.0（纯版本号，无预发布后缀）
        // dev 通道: v1.0.0-dev, v1.0.0-alpha, v1.0.0-beta, v1.0.0-rc.1 等
        $preReleaseSuffixes = ['-dev', '-alpha', '-beta', '-rc'];
        $isPreRelease = false;

        foreach ($preReleaseSuffixes as $suffix) {
            if (str_contains($tagName, $suffix)) {
                $isPreRelease = true;
                break;
            }
        }

        return ($channel === 'dev') === $isPreRelease;
    }

    /**
     * 移除预发布后缀用于版本比较
     */
    protected function stripPreReleaseSuffix(string $version): string
    {
        // 移除 -dev, -alpha, -alpha.1, -beta, -beta.2, -rc, -rc.1 等后缀
        return preg_replace('/-(dev|alpha|beta|rc)(\.\d+)?$/', '', $version);
    }

    /**
     * 标准化 Release 数据
     */
    protected function normalizeRelease(array $release): array
    {
        $tagName = $release['tag_name'] ?? '';
        $version = ltrim($tagName, 'vV');

        // 解析资源文件
        $assets = [];
        foreach ($release['assets'] ?? [] as $asset) {
            $assets[] = [
                'name' => $asset['name'] ?? '',
                'size' => $asset['size'] ?? 0,
                'browser_download_url' => $asset['browser_download_url'] ?? '',
            ];
        }

        return [
            'version' => $version,
            'tag_name' => $tagName,
            'name' => $release['name'] ?? $tagName,
            'body' => $release['body'] ?? '',
            'prerelease' => $release['prerelease'] ?? false,
            'created_at' => $release['created_at'] ?? '',
            'published_at' => $release['published_at'] ?? $release['created_at'] ?? '',
            'assets' => $assets,
        ];
    }

    // ==================== Local Provider ====================

    /**
     * Fetch local releases from releases.json
     */
    protected function fetchLocalReleases(): array
    {
        $baseUrl = rtrim($this->config['base_url'] ?? '', '/');
        $indexUrl = "$baseUrl/releases.json";

        try {
            $response = Http::timeout(10)->get($indexUrl);
            if ($response->successful()) {
                return $response->json()['releases'] ?? [];
            }
        } catch (\Exception $e) {
            Log::warning("Failed to fetch local releases.json: {$e->getMessage()}");
        }

        return [];
    }

    /**
     * Get latest local release
     */
    protected function getLatestLocalRelease(string $channel): ?array
    {
        $releases = $this->fetchLocalReleases();

        // Filter by channel and find highest version
        $latestRelease = null;
        $latestVersion = '0.0.0';

        foreach ($releases as $release) {
            $tagName = $release['tag_name'] ?? '';
            if ($this->matchChannel($tagName, $channel)) {
                $version = ltrim($tagName, 'vV');
                $compareVersion = $this->stripPreReleaseSuffix($version);
                $compareLatest = $this->stripPreReleaseSuffix($latestVersion);

                if (version_compare($compareVersion, $compareLatest, '>')) {
                    $latestVersion = $version;
                    $latestRelease = $release;
                }
            }
        }

        return $latestRelease ? $this->normalizeLocalRelease($latestRelease) : null;
    }

    /**
     * Get local release by tag
     */
    protected function getLocalReleaseByTag(string $tag): ?array
    {
        $releases = $this->fetchLocalReleases();

        foreach ($releases as $release) {
            if (($release['tag_name'] ?? '') === $tag) {
                return $this->normalizeLocalRelease($release);
            }
        }

        return null;
    }

    /**
     * Get local release history
     */
    protected function getLocalReleaseHistory(int $limit, string $channel): array
    {
        $releases = $this->fetchLocalReleases();
        $filtered = [];

        // Sort by version (descending)
        usort($releases, function ($a, $b) {
            $va = ltrim($a['tag_name'] ?? '', 'vV');
            $vb = ltrim($b['tag_name'] ?? '', 'vV');

            return version_compare($vb, $va);
        });

        foreach ($releases as $release) {
            $tagName = $release['tag_name'] ?? '';
            if ($this->matchChannel($tagName, $channel)) {
                $filtered[] = $this->normalizeLocalRelease($release);
                if (count($filtered) >= $limit) {
                    break;
                }
            }
        }

        return $filtered;
    }

    /**
     * Normalize local release data
     */
    protected function normalizeLocalRelease(array $release): array
    {
        $tagName = $release['tag_name'] ?? '';
        $version = ltrim($tagName, 'vV');
        $baseUrl = rtrim($this->config['base_url'] ?? '', '/');

        // Parse assets
        $assets = [];
        foreach ($release['assets'] ?? [] as $asset) {
            $url = $asset['browser_download_url'] ?? '';
            // Convert relative path to full URL
            if ($url && ! str_starts_with($url, 'http')) {
                $url = "$baseUrl/$url";
            }
            $assets[] = [
                'name' => $asset['name'] ?? '',
                'size' => $asset['size'] ?? 0,
                'browser_download_url' => $url,
            ];
        }

        return [
            'version' => $version,
            'tag_name' => $tagName,
            'name' => $release['name'] ?? $tagName,
            'body' => $release['body'] ?? '',
            'prerelease' => $release['prerelease'] ?? str_contains($tagName, '-dev'),
            'created_at' => $release['created_at'] ?? date('c'),
            'published_at' => $release['published_at'] ?? $release['created_at'] ?? date('c'),
            'assets' => $assets,
        ];
    }
}
