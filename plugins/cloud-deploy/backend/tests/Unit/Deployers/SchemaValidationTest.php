<?php

use Illuminate\Support\Facades\Validator;
use Plugins\CloudDeploy\Deployers\Registry;
use Plugins\CloudDeploy\Requests\Concerns\ValidatesAgainstSchema;
use Tests\TestCase;

uses(TestCase::class);

function validateDeployerSchema(string $provider, string $product, array $config): array
{
    $request = new class
    {
        use ValidatesAgainstSchema;

        public function check(string $provider, string $product, array $config): array
        {
            $validator = Validator::make([], []);
            $this->validateConfigSchema($validator, $provider, $product, $config);

            return $validator->errors()->keys();
        }
    };

    return $request->check($provider, $product, $config);
}

test('1Panel 三种部署分支只要求当前字段并拒绝错误枚举及隐藏旧值', function () {
    expect(validateDeployerSchema('onepanel', 'site', ['deploy_target' => 'website', 'website_match_pattern' => 'certsan']))->toBe([]);
    expect(validateDeployerSchema('onepanel', 'site', ['deploy_target' => 'website', 'website_match_pattern' => 'specified', 'website_id' => 18]))->toBe([]);
    expect(validateDeployerSchema('onepanel', 'site', ['deploy_target' => 'certificate', 'certificate_id' => 18]))->toBe([]);
    expect(validateDeployerSchema('onepanel', 'site', ['deploy_target' => 'website']))->toContain('config.website_id');
    expect(validateDeployerSchema('onepanel', 'site', ['deploy_target' => 'certificate']))->toContain('config.certificate_id');
    expect(validateDeployerSchema('onepanel', 'site', ['deploy_target' => 'example.com', 'website_match_pattern' => 'example.com']))->toContain('config.deploy_target', 'config.website_match_pattern');
    expect(validateDeployerSchema('onepanel', 'site', ['deploy_target' => 'website', 'website_match_pattern' => 'certsan', 'website_id' => 18]))->toContain('config.website_id');
    expect(validateDeployerSchema('onepanel', 'site', ['deploy_target' => 'certificate', 'certificate_id' => 18, 'website_id' => 1]))->toContain('config.website_id');
});

test('all、any、not_equals 联动在服务端保存入口生效', function () {
    expect(validateDeployerSchema('nginxproxymanager', 'certificate', ['deploy_target' => 'host', 'host_type' => 'proxy', 'host_match_pattern' => 'certsan']))->toBe([]);
    expect(validateDeployerSchema('nginxproxymanager', 'certificate', ['deploy_target' => 'host', 'host_type' => 'proxy']))->toContain('config.host_id');
    expect(validateDeployerSchema('tencent', 'clb', ['deploy_target' => 'loadbalancer', 'load_balancer_id' => 'lb', 'region' => 'r']))->toBe([]);
    expect(validateDeployerSchema('tencent', 'clb', ['deploy_target' => 'ruledomain', 'load_balancer_id' => 'lb', 'region' => 'r']))->toContain('config.listener_id', 'config.domain');
    expect(validateDeployerSchema('ratpanel', 'site', ['deploy_target' => 'certificate', 'certificate_id' => 18]))->toBe([]);
    expect(validateDeployerSchema('ratpanel', 'site', ['site_names' => 'example.com']))->toBe([]);
});

test('全量有限选项在保存入口拒绝任意文本值', function () {
    foreach (app(Registry::class)->allDeployers() as ['provider' => $provider, 'product' => $product]) {
        foreach (app(Registry::class)->resolveDeployer($provider, $product)->configSchema() as $field) {
            if (($field['type'] ?? null) === 'select') {
                expect(validateDeployerSchema($provider, $product, [$field['key'] => '__invalid_option__']))
                    ->toContain('config.'.$field['key']);
            }
        }
    }
});

test('支持证书自动匹配的全部部署器均允许省略域名输入', function () {
    foreach (app(Registry::class)->allDeployers() as ['provider' => $provider, 'product' => $product]) {
        $fields = array_column(app(Registry::class)->resolveDeployer($provider, $product)->configSchema(), null, 'key');
        if (! in_array('certsan', array_column($fields['domain_match_pattern']['options'] ?? [], 'value'), true)) {
            continue;
        }
        $errors = validateDeployerSchema($provider, $product, ['domain_match_pattern' => 'certsan']);
        expect(in_array('config.domain', $errors, true))->toBeFalse("$provider.$product certsan 不应要求 domain");
        expect(in_array('config.domains', $errors, true))->toBeFalse("$provider.$product certsan 不应要求 domains");
    }
});
