<?php

namespace Plugins\CloudDeploy\Deployers\Volcengine;

use Plugins\CloudDeploy\Deployers\Contracts\AbstractDeployer;
use Plugins\CloudDeploy\Deployers\Contracts\CertUploaderInterface;
use Throwable;

/**
 * 火山引擎 CLB 传统型负载均衡（证书服务型）：证书经证书中心上传拿 InstanceId（走 RemoteCertStore 去重），
 * 再设到 CLB 的 HTTPS 监听器。对齐 certimate volcengine-clb（universal/RPC query 协议，GET /）：
 *   - deploy_target=loadbalancer：DescribeListeners(Protocol=HTTPS) 枚举 → 逐个 ModifyListenerAttributes
 *   - deploy_target=listener：查询监听器后更新默认或 SNI 证书
 *   ModifyListenerAttributes {ListenerId, CertificateSource:"cert_center", CertCenterCertificateId}
 *   （Action=ModifyListenerAttributes / DescribeListeners, Version=2020-04-01）
 *
 * region 必填（CLB 为 region 维度）；证书中心上传与 CLB 用同一 region。
 */
class VolcClbDeployer extends AbstractDeployer
{
    public function provider(): string
    {
        return 'volcengine';
    }

    public function product(): string
    {
        return 'clb';
    }

    public function label(): string
    {
        return '火山引擎 CLB';
    }

    public function configSchema(): array
    {
        return [
            ['key' => 'region', 'label' => '地域', 'type' => 'string', 'required' => true],
            ['key' => 'deploy_target', 'label' => '证书部署方式', 'type' => 'select', 'required' => true, 'default' => 'listener', 'options' => [
                ['label' => '负载均衡实例（全部 HTTPS 监听）', 'value' => 'loadbalancer'],
                ['label' => '指定监听器', 'value' => 'listener'],
            ]],
            ['key' => 'loadbalancer_id', 'label' => '负载均衡实例 ID（部署目标为实例时必填）', 'type' => 'string', 'required' => false, 'visible_when' => ['key' => 'deploy_target', 'equals' => 'loadbalancer'], 'required_when' => ['key' => 'deploy_target', 'equals' => 'loadbalancer']],
            ['key' => 'domain', 'label' => 'SNI 域名（选填，留空更新默认证书）', 'type' => 'string', 'required' => false, 'visible_when' => ['any' => [['key' => 'deploy_target', 'equals' => 'loadbalancer'], ['key' => 'deploy_target', 'equals' => 'listener']]]],
            ['key' => 'listener_id', 'label' => '监听器 ID（部署目标为指定监听器时必填）', 'type' => 'string', 'required' => false, 'visible_when' => ['key' => 'deploy_target', 'equals' => 'listener'], 'required_when' => ['key' => 'deploy_target', 'equals' => 'listener']],
        ];
    }

    public function usesRemoteCertStore(): bool
    {
        return true;
    }

    public function certUploader(array $config = []): ?CertUploaderInterface
    {
        // RegistryCompleteness 以空 config 探活：region 优雅默认（真实 region 由 bind 强校验），不抛
        $region = (string) ($config['region'] ?? '');

        return new VolcCertCenterUploader(
            fn (array $credentials): object => $this->makeClient('certcenter', $credentials, $region),
        );
    }

    /**
     * @param  string  $certRef  证书中心 InstanceId
     * @param  array{access_key_id?:string,secret_access_key?:string}  $credentials
     * @param  array{region:string,deploy_target:string,loadbalancer_id?:string,listener_id?:string,domain?:string}  $config
     */
    public function bind(string|array $certRef, array $credentials, array $config): void
    {
        // 业务校验全在 guardSdk 之外（业务错误不进 guardSdk，否则被重建成无 message 的 SDK 异常）
        $region = (string) $this->requireConfig($config, 'region');
        $deployTarget = (string) $this->requireConfig($config, 'deploy_target');
        $certId = (string) $certRef;

        $loadbalancerId = '';
        $listenerId = '';
        if ($deployTarget === 'loadbalancer') {
            $loadbalancerId = (string) $this->requireConfig($config, 'loadbalancer_id');
        } elseif ($deployTarget === 'listener') {
            $listenerId = (string) $this->requireConfig($config, 'listener_id');
        } else {
            $this->fail("不支持的部署目标 $deployTarget");
        }

        $this->guardSdk(function () use ($credentials, $deployTarget, $loadbalancerId, $listenerId, $certId, $region, $config) {
            /** @var VolcRestClient $client */
            $client = $this->makeClient('clb', $credentials, $region);

            $listenerIds = $deployTarget === 'loadbalancer'
                ? $this->listHttpsListeners($client, $loadbalancerId)
                : [$listenerId];

            foreach ($listenerIds as $id) {
                $domain = (string) ($config['domain'] ?? '');
                $attrs = $client->callQuery('DescribeListenerAttributes', '2020-04-01', ['ListenerId' => $id]);
                if ($domain === '') {
                    if (($attrs['CertCenterCertificateId'] ?? '') !== $certId) {
                        $client->callQuery('ModifyListenerAttributes', '2020-04-01', [
                            'ListenerId' => $id, 'CertificateSource' => 'cert_center', 'CertCenterCertificateId' => $certId,
                        ]);
                    }

                    continue;
                }
                $extension = null;
                foreach ($attrs['DomainExtensions'] ?? [] as $item) {
                    if (($item['Domain'] ?? '') === $domain) {
                        $extension = $item;
                        break;
                    }
                }
                if ($extension === null) {
                    throw new VolcApiException('DomainNotFound', '未找到监听器 SNI 扩展域名');
                }
                if (($extension['CertCenterCertificateId'] ?? '') === $certId) {
                    continue;
                }
                $client->callQuery('ModifyListenerDomainExtensions', '2020-04-01', [
                    'ListenerId' => $id,
                    'ModifyDomainExtensions' => [[
                        'DomainExtensionId' => $extension['DomainExtensionId'],
                        'CertificateSource' => 'cert_center', 'CertCenterCertificateId' => $certId,
                    ]],
                ]);
            }
        });
    }

    /**
     * 枚举实例下全部 HTTPS 监听器 id（DescribeListeners 分页，universal query GET）。
     *
     * @return list<string>
     */
    private function listHttpsListeners(VolcRestClient $client, string $loadbalancerId): array
    {
        $ids = [];
        $page = 1;
        $pageSize = 100;
        do {
            $result = $client->callQuery('DescribeListeners', '2020-04-01', [
                'LoadBalancerId' => $loadbalancerId,
                'Protocol' => 'HTTPS',
                'PageNumber' => $page,
                'PageSize' => $pageSize,
            ]);

            $listeners = is_array($result['Listeners'] ?? null) ? $result['Listeners'] : [];
            foreach ($listeners as $listener) {
                $id = is_array($listener) ? ($listener['ListenerId'] ?? null) : null;
                if (is_string($id) && $id !== '') {
                    $ids[] = $id;
                }
            }
            $page++;
        } while (count($listeners) >= $pageSize);

        return $ids;
    }

    protected function makeClient(string $kind, array $credentials, string $region = ''): object
    {
        return match ($kind) {
            'clb' => new VolcRestClient(
                VolcRestClient::OPEN_HOST,
                'clb',
                $region,
                $credentials['access_key_id'] ?? '',
                $credentials['secret_access_key'] ?? '',
            ),
            'certcenter' => new VolcRestClient(
                VolcRestClient::OPEN_HOST,
                'certificate_service',
                $region,
                $credentials['access_key_id'] ?? '',
                $credentials['secret_access_key'] ?? '',
            ),
            default => throw new \InvalidArgumentException("不支持的客户端类型: $kind"),
        };
    }

    protected function sanitize(Throwable $e): string
    {
        return VolcErrorSanitizer::sanitize($e);
    }
}
