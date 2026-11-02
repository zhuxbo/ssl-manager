<?php

namespace Plugins\CloudDeploy\Deployers\Aliyun;

use AlibabaCloud\SDK\Cas\V20200407\Cas;
use AlibabaCloud\SDK\Mse\V20190531\Models\ListGatewayDomainRequest;
use AlibabaCloud\SDK\Mse\V20190531\Models\UpdateGatewayDomainRequest;
use AlibabaCloud\SDK\Mse\V20190531\Models\UpdateSSLCertRequest;
use AlibabaCloud\SDK\Mse\V20190531\Mse;
use Plugins\CloudDeploy\Deployers\Contracts\AbstractDeployer;
use Plugins\CloudDeploy\Deployers\Contracts\CertUploaderInterface;
use Plugins\CloudDeploy\Deployers\Contracts\ReceivesRemoteCertificateMaterial;
use Throwable;

class AliyunMseDeployer extends AbstractDeployer implements ReceivesRemoteCertificateMaterial
{
    use BuildsAliyunConfig, MatchesAliyunDomains;

    public function provider(): string
    {
        return 'aliyun';
    }

    public function product(): string
    {
        return 'mse';
    }

    public function label(): string
    {
        return '阿里云 MSE 云原生网关';
    }

    public function usesRemoteCertStore(): bool
    {
        return true;
    }

    public function configSchema(): array
    {
        return [
            ['key' => 'region', 'label' => '地域', 'type' => 'string', 'required' => true],
            ['key' => 'gateway_id', 'label' => '云原生网关 ID', 'type' => 'string', 'required' => true],
            ['key' => 'domain_match_pattern', 'label' => '域名匹配模式', 'type' => 'select', 'required' => false, 'default' => 'exact', 'options' => [
                ['label' => '精确匹配', 'value' => 'exact'],
                ['label' => '泛域名匹配', 'value' => 'wildcard'],
                ['label' => '证书域名匹配', 'value' => 'certsan'],
            ]],
            ['key' => 'domain', 'label' => '域名（证书匹配时可留空）', 'type' => 'string', 'required' => false, 'visible_when' => ['key' => 'domain_match_pattern', 'not_equals' => 'certsan'], 'required_when' => ['key' => 'domain_match_pattern', 'not_equals' => 'certsan']],
        ];
    }

    public function certUploader(array $config = []): ?CertUploaderInterface
    {
        $region = trim((string) ($config['region'] ?? ''));
        $casRegion = $region !== '' && ! str_starts_with($region, 'cn-') ? 'ap-southeast-1' : '';

        return new AliyunCasUploader(fn (array $credentials): object => $this->makeClient('cas', $credentials), $casRegion);
    }

    public function bind(string|array $certRef, array $credentials, array $config): void
    {
        $region = (string) $this->requireConfig($config, 'region');
        $gateway = (string) $this->requireConfig($config, 'gateway_id');
        if (($config['service_type'] ?? 'cloudnative') !== 'cloudnative') {
            $this->fail('MSE 仅支持云原生网关');
        }
        $pattern = strtolower((string) ($config['domain_match_pattern'] ?? 'exact')) ?: 'exact';
        if (! in_array($pattern, ['exact', 'wildcard', 'certsan'], true)) {
            $this->fail('不支持的域名匹配模式');
        }
        $domain = $pattern === 'certsan' ? '' : (string) $this->requireConfig($config, 'domain');
        $certId = is_array($certRef) ? (string) ($certRef['remote_cert_id'] ?? '') : $certRef;
        if ($certId === '') {
            $this->fail('缺少远端证书标识');
        }
        /** @var Mse $client */
        $client = $this->makeClient('mse', $credentials + ['region' => $region]);
        $data = $this->guardSdk(fn () => $this->responseData($client->listGatewayDomain(new ListGatewayDomainRequest(['gatewayUniqueId' => $gateway]))->toMap()));
        $matched = array_filter($data, fn (array $item): bool => ! in_array((int) ($item['Status'] ?? 0), [5, 6], true) && match ($pattern) {
            'exact' => ($item['Name'] ?? '') === $domain,
            'wildcard' => $this->hostnameMatches($domain, (string) ($item['Name'] ?? '')),
            'certsan' => $this->certificateMatches(is_array($certRef) ? (string) ($certRef['cert'] ?? '') : '', (string) ($item['Name'] ?? '')),
        });
        if ($matched === []) {
            $this->fail('未找到匹配的 MSE 网关域名');
        }
        foreach ($matched as $item) {
            $https = strcasecmp((string) ($item['Protocol'] ?? ''), 'HTTPS') === 0;
            if ($https && ($item['CertIdentifier'] ?? '') === $certId) {
                continue;
            }
            $this->guardSdk(function () use ($client, $gateway, $certId, $item, $https): void {
                $response = $https
                    ? $client->updateSSLCert(new UpdateSSLCertRequest(['gatewayUniqueId' => $gateway, 'domainId' => $item['Id'], 'certIdentifier' => $certId]))
                    : $client->updateGatewayDomain(new UpdateGatewayDomainRequest([
                        'gatewayUniqueId' => $gateway, 'id' => $item['Id'], 'protocol' => 'HTTPS',
                        'certIdentifier' => $certId, 'mustHttps' => false,
                        'tlsMin' => ($item['TlsMin'] ?? '') ?: 'TLS 1.2', 'tlsMax' => ($item['TlsMax'] ?? '') ?: 'TLS 1.3',
                    ]));
                $this->responseData($response->toMap());
            });
        }
    }

    private function responseData(array $response): array
    {
        $body = $response['body'] ?? [];
        if (($body['Success'] ?? false) !== true) {
            throw new \RuntimeException('MSE 接口未返回成功状态');
        }

        return is_array($body['Data'] ?? null) ? $body['Data'] : [];
    }

    protected function makeClient(string $kind, array $credentials): object
    {
        return match ($kind) {
            'cas' => new Cas($this->aliyunConfig($credentials, $this->casEndpoint($credentials))),
            'mse' => new Mse($this->aliyunConfig($credentials, 'mse.'.($credentials['region'] ?? '').'.aliyuncs.com')),
        };
    }

    protected function sanitize(Throwable $e): string
    {
        return AliyunErrorSanitizer::sanitize($e);
    }
}
