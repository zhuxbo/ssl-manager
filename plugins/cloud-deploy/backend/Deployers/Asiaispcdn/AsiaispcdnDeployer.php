<?php

namespace Plugins\CloudDeploy\Deployers\Asiaispcdn;

use GuzzleHttp\Client;
use Plugins\CloudDeploy\Deployers\Aliyun\MatchesAliyunDomains;
use Plugins\CloudDeploy\Deployers\Contracts\AbstractDeployer;
use Plugins\CloudDeploy\Deployers\Contracts\CertUploaderInterface;
use Plugins\CloudDeploy\Deployers\Contracts\ReceivesRemoteCertificateMaterial;
use RuntimeException;
use Throwable;

class AsiaispcdnDeployer extends AbstractDeployer implements ReceivesRemoteCertificateMaterial
{
    use MatchesAliyunDomains;

    public function provider(): string
    {
        return 'asiaispcdn';
    }

    public function product(): string
    {
        return 'cdn';
    }

    public function label(): string
    {
        return '橙域网络 CDN';
    }

    public function usesRemoteCertStore(): bool
    {
        return true;
    }

    public function certUploader(array $config = []): ?CertUploaderInterface
    {
        return new AsiaispcdnUploader(fn (array $credentials): object => $this->makeClient('api', $credentials));
    }

    public function configSchema(): array
    {
        return [
            ['key' => 'domain', 'label' => '加速域名（证书匹配时可留空）', 'type' => 'string', 'required' => false, 'visible_when' => ['key' => 'domain_match_pattern', 'not_equals' => 'certsan'], 'required_when' => ['key' => 'domain_match_pattern', 'not_equals' => 'certsan']],
            ['key' => 'domain_match_pattern', 'label' => '域名匹配模式', 'type' => 'select', 'required' => false, 'default' => 'exact', 'options' => [
                ['label' => '精确匹配', 'value' => 'exact'],
                ['label' => '泛域名匹配', 'value' => 'wildcard'],
                ['label' => '证书匹配', 'value' => 'certsan'],
            ]],
        ];
    }

    public function bind(string|array $certRef, array $credentials, array $config): void
    {
        $pattern = strtolower((string) ($config['domain_match_pattern'] ?? 'exact')) ?: 'exact';
        if (! in_array($pattern, ['exact', 'wildcard', 'certsan'], true)) {
            $this->fail('不支持的域名匹配模式');
        }
        $domain = $pattern === 'certsan' ? '' : (string) $this->requireConfig($config, 'domain');
        $certId = is_array($certRef) ? (string) ($certRef['remote_cert_id'] ?? '') : $certRef;
        if (! ctype_digit($certId) || (int) $certId <= 0) {
            $this->fail('无效的远端证书 ID');
        }
        /** @var AsiaispcdnClient $client */
        $client = $this->makeClient('api', $credentials);
        $exact = $pattern === 'exact' || ($pattern === 'wildcard' && ! str_starts_with($domain, '*.'));
        $items = $this->guardSdk(fn () => $client->call('GET', 'domainQueryList', $exact ? ['domain' => $domain] : []));
        if (! is_array($items)) {
            $this->fail('未返回 CDN 域名列表');
        }
        $matched = [];
        foreach ($items as $item) {
            $candidate = (string) ($item['domain'] ?? '');
            if ($candidate === '' || (! $exact && (int) ($item['domainStatus'] ?? 0) === 0)) {
                continue;
            }
            if ($exact ? $candidate === $domain : ($pattern === 'wildcard' ? $this->hostnameMatches($domain, $candidate) : $this->certificateMatches(is_array($certRef) ? (string) ($certRef['cert'] ?? '') : '', $candidate))) {
                $matched[] = $item;
            }
        }
        if ($matched === []) {
            $this->fail('未找到匹配的 CDN 域名');
        }
        foreach ($matched as $item) {
            if ((string) ($item['certId'] ?? '') === $certId) {
                continue;
            }
            // 操作中的域名交由队列退避重试，避免在 55 秒 Job 内无限等待。
            if ((int) ($item['domainStatus'] ?? 0) === 2) {
                throw new RuntimeException('AsiaISP CDN 域名正在操作中，请稍后重试');
            }
            $this->guardSdk(fn () => $client->call('PUT', 'domainModify', ['domain' => $item['domain'], 'protocol' => 'https', 'certId' => (int) $certId]));
        }
    }

    protected function makeClient(string $kind, array $credentials): object
    {
        return new AsiaispcdnClient(new Client(['timeout' => 10, 'connect_timeout' => 3]), (string) ($credentials['access_key_id'] ?? ''), (string) ($credentials['access_key_secret'] ?? ''));
    }

    protected function sanitize(Throwable $e): string
    {
        return 'AsiaISP CDN 调用失败: '.class_basename($e);
    }
}
