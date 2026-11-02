<?php

namespace Plugins\CloudDeploy\Deployers\Ratpanel;

use Plugins\CloudDeploy\Deployers\Contracts\AbstractDeployer;
use Throwable;

/**
 * 耗子面板 网站证书（内联型，product=site）。
 *
 * 对齐 certimate ratpanel（deployTarget=website）：替换指定网站的 SSL 证书（POST /website/cert，每站点一次）。
 * - 完整链（certRef.cert + certRef.chain）→ 请求体 cert
 * - 私钥（certRef.key）→ 请求体 key
 * - 站点名（config.site_names，`;`/换行/逗号分隔多站点）→ 请求体 name
 *
 * 多站点逐个调用、**尝试全部并聚合错误**（与 certimate errors.Join 一致：单站点失败不中断其余）。
 * 内联型（usesRemoteCertStore=false）：bind 收 {cert,key,chain} 三元组。鉴权 HMAC-SHA256 签名。
 *
 * config：deploy_target 默认 website；site_names（网站分支必填，可多），certificate_id（证书分支必填）。server_url / access_token_id / access_token / allow_insecure 归 credentialSchema。
 */
class RatpanelSiteDeployer extends AbstractDeployer
{
    use BuildsRatpanelClient;

    public function provider(): string
    {
        return 'ratpanel';
    }

    public function product(): string
    {
        return 'site';
    }

    public function label(): string
    {
        return '耗子面板 网站';
    }

    public function configSchema(): array
    {
        return [
            ['key' => 'deploy_target', 'label' => '证书部署方式', 'type' => 'select', 'required' => false, 'default' => 'website', 'options' => [
                ['label' => '网站', 'value' => 'website'],
                ['label' => '替换指定证书', 'value' => 'certificate'],
            ]],
            ['key' => 'site_names', 'label' => '站点名（多个用分号/换行分隔）', 'type' => 'string', 'required' => false,
                'visible_when' => ['key' => 'deploy_target', 'equals' => 'website'],
                'required_when' => ['key' => 'deploy_target', 'equals' => 'website']],
            ['key' => 'certificate_id', 'label' => '证书 ID', 'type' => 'number', 'required' => false,
                'visible_when' => ['key' => 'deploy_target', 'equals' => 'certificate'],
                'required_when' => ['key' => 'deploy_target', 'equals' => 'certificate']],
        ];
    }

    /**
     * @param  array{cert:string,key:string,chain:string}|string  $certRef  内联 PEM 三元组
     * @param  array<string,mixed>  $credentials
     * @param  array{deploy_target?:string,site_names?:string,certificate_id?:int|string}  $config
     */
    public function bind(string|array $certRef, array $credentials, array $config): void
    {
        $target = (string) ($config['deploy_target'] ?? 'website');
        if ($target === 'certificate') {
            $this->replaceCertificate($certRef, $credentials, $config);

            return;
        }
        if ($target !== 'website') {
            $this->fail('不支持的部署目标：'.$target);
        }
        $siteNames = $this->parseSiteNames($this->requireConfig($config, 'site_names'));
        if ($siteNames === []) {
            $this->fail('缺少配置 site_names');
        }

        // 完整链（leaf + 中间证书），与 certimate 传 certPEM 完整链一致
        $fullChain = rtrim($certRef['cert']);
        if (trim($certRef['chain']) !== '') {
            $fullChain .= "\n".trim($certRef['chain']);
        }
        $key = $certRef['key'];

        // 逐站点更新，尝试全部并聚合错误（对齐 certimate errors.Join）
        $errors = [];
        foreach ($siteNames as $siteName) {
            try {
                $this->guardSdk(function () use ($credentials, $siteName, $fullChain, $key) {
                    $this->makeClient('api', $credentials)->setWebsiteCert($siteName, $fullChain, $key);
                });
            } catch (Throwable $e) {
                $errors[] = "[$siteName] ".$e->getMessage();
            }
        }

        if ($errors !== []) {
            $this->fail(implode('; ', $errors));
        }
    }

    /** @param array{cert:string,key:string,chain:string} $certRef */
    private function replaceCertificate(array $certRef, array $credentials, array $config): void
    {
        $certificateId = filter_var($this->requireConfig($config, 'certificate_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($certificateId === false) {
            $this->fail('证书 ID 必须为正整数');
        }
        $parsed = openssl_x509_parse($certRef['cert']);
        if ($parsed === false) {
            $this->fail('无法解析证书');
        }
        preg_match_all('/(?:^|,\s*)DNS:([^,]+)/', (string) ($parsed['extensions']['subjectAltName'] ?? ''), $matches);
        $domains = array_map('trim', $matches[1]);
        $fullChain = rtrim($certRef['cert']);
        if (trim($certRef['chain']) !== '') {
            $fullChain .= "\n".trim($certRef['chain']);
        }
        $this->guardSdk(function () use ($credentials, $certificateId, $domains, $fullChain, $certRef) {
            $this->makeClient('api', $credentials)->updateCertificate($certificateId, $domains, $fullChain, $certRef['key']);
        });
    }

    protected function makeClient(string $kind, array $credentials): object
    {
        return match ($kind) {
            'api' => $this->makeRatpanelClient($credentials),
        };
    }

    protected function sanitize(Throwable $e): string
    {
        return RatpanelErrorSanitizer::sanitize($e);
    }

    /**
     * 解析站点名列表（分号/换行/逗号分隔，去空去重）。
     *
     * @return list<string>
     */
    private function parseSiteNames(mixed $raw): array
    {
        $items = is_array($raw) ? $raw : (preg_split('/[\r\n;,，；]+/u', (string) $raw) ?: []);
        $out = [];
        foreach ($items as $item) {
            $item = trim((string) $item);
            if ($item !== '') {
                $out[] = $item;
            }
        }

        return array_values(array_unique($out));
    }
}
