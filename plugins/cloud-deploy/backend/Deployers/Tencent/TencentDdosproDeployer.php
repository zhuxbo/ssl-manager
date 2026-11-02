<?php

namespace Plugins\CloudDeploy\Deployers\Tencent;

use Plugins\CloudDeploy\Deployers\Aliyun\MatchesAliyunDomains;
use Plugins\CloudDeploy\Deployers\Contracts\AbstractDeployer;
use Plugins\CloudDeploy\Deployers\Contracts\CertUploaderInterface;
use TencentCloud\Antiddos\V20200309\AntiddosClient;
use TencentCloud\Antiddos\V20200309\Models\DescribeL7RulesBySSLCertIdRequest;
use TencentCloud\Antiddos\V20200309\Models\DescribeNewL7RulesRequest;
use TencentCloud\Antiddos\V20200309\Models\ModifyNewDomainRulesRequest;
use TencentCloud\Common\Credential;
use TencentCloud\Common\Profile\ClientProfile;
use TencentCloud\Common\Profile\HttpProfile;
use TencentCloud\Ssl\V20191205\SslClient;
use Throwable;

class TencentDdosproDeployer extends AbstractDeployer
{
    use MatchesAliyunDomains, UsesTencentEndpoint;

    public function provider(): string
    {
        return 'tencent';
    }

    public function product(): string
    {
        return 'ddospro';
    }

    public function label(): string
    {
        return '腾讯云 DDoS 高防';
    }

    public function usesRemoteCertStore(): bool
    {
        return true;
    }

    public function configSchema(): array
    {
        return [
            ['key' => 'instance_id', 'label' => '高防实例 ID', 'type' => 'string', 'required' => true],
            ['key' => 'domain', 'label' => '域名（证书匹配时可留空）', 'type' => 'string', 'required' => false, 'visible_when' => ['key' => 'domain_match_pattern', 'not_equals' => 'certsan'], 'required_when' => ['key' => 'domain_match_pattern', 'not_equals' => 'certsan']],
            ['key' => 'domain_match_pattern', 'label' => '域名匹配模式', 'type' => 'select', 'required' => false, 'default' => 'exact', 'options' => [
                ['label' => '精确匹配', 'value' => 'exact'],
                ['label' => '泛域名匹配', 'value' => 'wildcard'],
                ['label' => '证书匹配', 'value' => 'certsan'],
            ]],
            ['key' => 'endpoint', 'label' => '接口端点（选填）', 'type' => 'string', 'required' => false],
        ];
    }

    public function certUploader(array $config = []): ?CertUploaderInterface
    {
        return new TencentSslUploader(fn (array $credentials): object => $this->makeClient('ssl', $this->withTencentEndpoint($credentials, $config)));
    }

    public function bind(string|array $certRef, array $credentials, array $config): void
    {
        $instance = (string) $this->requireConfig($config, 'instance_id');
        $pattern = strtolower((string) ($config['domain_match_pattern'] ?? 'exact')) ?: 'exact';
        if (! in_array($pattern, ['exact', 'wildcard', 'certsan'], true)) {
            $this->fail('不支持的域名匹配模式');
        }
        $domain = $pattern === 'certsan' ? '' : (string) $this->requireConfig($config, 'domain');
        $certId = (string) $certRef;
        /** @var AntiddosClient $client */
        $client = $this->makeClient('antiddos', $this->withTencentEndpoint($credentials, $config));
        $domains = [$domain];
        if ($pattern === 'certsan' || ($pattern === 'wildcard' && str_starts_with($domain, '*.'))) {
            $result = $this->guardSdk(function () use ($client, $certId): array {
                $request = new DescribeL7RulesBySSLCertIdRequest;
                $request->deserialize(['Status' => 'all', 'CertIds' => [$certId]]);

                return json_decode($client->DescribeL7RulesBySSLCertId($request)->toJsonString(), true);
            });
            $domains = [];
            foreach ($result['CertSet'][0]['L7Rules'] ?? [] as $rule) {
                $candidate = (string) ($rule['Domain'] ?? '');
                if (($rule['InsId'] ?? '') === $instance && $candidate !== '' && ($pattern === 'certsan' || $this->hostnameMatches($domain, $candidate))) {
                    $domains[] = $candidate;
                }
            }
        }
        if ($domains === []) {
            $this->fail('未找到匹配的高防域名');
        }
        foreach (array_unique($domains) as $candidate) {
            $rule = $this->guardSdk(fn () => $this->findRule($client, $instance, $candidate));
            if ($rule === null) {
                $this->fail('未找到高防域名规则');
            }
            if (($rule['CertType'] ?? null) === 2 && ($rule['SSLId'] ?? '') === $certId) {
                continue;
            }
            $this->guardSdk(function () use ($client, $instance, $rule, $certId): void {
                $fields = ['Protocol', 'LbType', 'Domain', 'KeepEnable', 'KeepTime', 'SourceType', 'SourceList', 'Region', 'Id', 'Ip', 'RuleId', 'RuleName', 'CCStatus', 'CCEnable', 'CCThresholdNew', 'CCLevel', 'HttpsToHttpEnable', 'VirtualPort', 'RewriteHttps'];
                $payload = array_intersect_key($rule, array_flip($fields));
                $payload['CertType'] = 2;
                $payload['SSLId'] = $certId;
                $request = new ModifyNewDomainRulesRequest;
                $request->deserialize(['Business' => 'bgpip', 'Id' => $instance, 'Rule' => $payload]);
                $client->ModifyNewDomainRules($request);
            });
        }
    }

    private function findRule(AntiddosClient $client, string $instance, string $domain): ?array
    {
        for ($offset = 0; ; $offset += 100) {
            $request = new DescribeNewL7RulesRequest;
            $request->deserialize(['Business' => 'bgpip', 'Domain' => $domain, 'Offset' => $offset, 'Limit' => 100]);
            $result = json_decode($client->DescribeNewL7Rules($request)->toJsonString(), true);
            $rules = $result['Rules'] ?? [];
            foreach ($rules as $rule) {
                if (($rule['Domain'] ?? '') === $domain && ($rule['Id'] ?? '') === $instance) {
                    return $rule;
                }
            }
            if (count($rules) < 100) {
                return null;
            }
        }
    }

    protected function makeClient(string $kind, array $credentials): object
    {
        $cred = new Credential($credentials['secret_id'] ?? '', $credentials['secret_key'] ?? '');
        $http = new HttpProfile;
        $http->setReqTimeout(15);
        $this->configureTencentEndpoint($http, $credentials, $kind);
        $profile = new ClientProfile;
        $profile->setHttpProfile($http);

        return match ($kind) {
            'ssl' => new SslClient($cred, '', $profile),
            'antiddos' => new AntiddosClient($cred, '', $profile),
        };
    }

    protected function sanitize(Throwable $e): string
    {
        return TencentErrorSanitizer::sanitize($e);
    }
}
