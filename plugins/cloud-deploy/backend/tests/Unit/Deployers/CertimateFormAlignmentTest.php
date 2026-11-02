<?php

use Plugins\CloudDeploy\Deployers\Registry;
use Plugins\CloudDeploy\Requests\Concerns\ValidatesAgainstSchema;
use Tests\TestCase;

uses(TestCase::class);

require_once __DIR__.'/CertimateAlignmentHelpers.php';

function certimateFormFixture(): array
{
    return json_decode(file_get_contents(dirname(__DIR__, 2).'/Fixtures/certimate-form-contracts.json'), true, flags: JSON_THROW_ON_ERROR);
}

function certimateLocalField(string $key, array $fields): string
{
    $key = strtolower(preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $key));
    if ($key === 'loadbalancer_id' && isset($fields['load_balancer_id'])) {
        return 'load_balancer_id';
    }

    return match ($key) {
        'object_key_for_crt_only_server' => 'object_key_for_crt_server',
        'object_key_for_crt_only_intermedia' => 'object_key_for_crt_inter',
        default => $key,
    };
}

function certimateLocalValue(string $endpoint, string $key, mixed $value): mixed
{
    return $endpoint === 's3.s3' && $key === 'file_format' && is_string($value) ? strtolower($value) : $value;
}

function certimateLocalCondition(array $condition, string $endpoint, array $fields): array
{
    foreach (['all', 'any'] as $operator) {
        if (isset($condition[$operator])) {
            return [$operator => array_map(fn ($item) => certimateLocalCondition($item, $endpoint, $fields), $condition[$operator])];
        }
    }
    $key = certimateLocalField($condition['key'], $fields);
    $operator = array_key_exists('equals', $condition) ? 'equals' : 'not_equals';

    return ['key' => $key, $operator => certimateLocalValue($endpoint, $key, $condition[$operator])];
}

function certimateSchemaConditionEvaluator(): object
{
    return new class
    {
        use ValidatesAgainstSchema;

        public function visible(array $field, array $schema, array $values): bool
        {
            return $this->isVisibleField($field, $schema, $values);
        }
    };
}

test('表单基准和目录基准必须来自同一上游提交且覆盖全部端点', function () {
    $forms = certimateFormFixture();
    $catalog = cloudDeployCertimateFixture();
    expect($forms['source']['sha'])->toBe($catalog['source']['sha']);
    $actual = cloudDeployNormalizeSourceSlugs(
        array_merge(array_keys($forms['forms']), $forms['without_form']),
        $catalog['source_slug_aliases']['sp_files'], $catalog['unsupported'],
    );
    expect($actual)->toBe(cloudDeploySupportedCoreSlugs($catalog));
});

test('所有部署表单的有限选项与上游一致且使用选择器', function () {
    $fixture = certimateFormFixture();
    $catalog = cloudDeployCertimateFixture();
    $forms = [];
    foreach ($fixture['forms'] as $slug => $form) {
        $forms[$catalog['source_slug_aliases']['sp_files'][$slug] ?? $slug] = $form;
    }
    // 保留旧配置的无副作用默认值；不能把 NPM 的证书替换或宝塔普通站点改成另一部署分支。
    $defaultOverrides = [
        'nginxproxymanager.certificate.deploy_target' => 'certificate',
        'baotapanel.site.site_type' => '',
        'baotapanelgo.site.site_type' => '',
    ];
    $extraOptions = ['baotapanel.site.site_type' => [''], 'baotapanelgo.site.site_type' => ['', 'aspx']];
    $errors = [];
    foreach (app(Registry::class)->allDeployers() as ['provider' => $provider, 'product' => $product]) {
        $endpoint = "$provider.$product";
        $form = $forms[cloudDeployPluginSlug($catalog, $provider, $product)] ?? null;
        if ($form === null) {
            continue;
        }
        $schema = app(Registry::class)->resolveDeployer($provider, $product)->configSchema();
        $fields = array_column($schema, null, 'key');
        foreach ($form['fields'] as $upstreamKey => $source) {
            if (! isset($source['options'])) {
                continue;
            }
            $key = certimateLocalField($upstreamKey, $fields);
            $where = "$endpoint.$key";
            // 只有一个取值且本地没有选择字段时是固定能力，不要求添加无意义的选择器。
            if (! isset($fields[$key]) && count($source['options']) === 1) {
                continue;
            }
            $field = $fields[$key] ?? [];
            $expected = array_map(fn ($v) => certimateLocalValue($endpoint, $key, $v), $source['options']);
            $expected = array_merge($expected, $extraOptions[$where] ?? []);
            $actual = array_column($field['options'] ?? [], 'value');
            sort($expected);
            sort($actual);
            if (($field['type'] ?? null) !== 'select' || $actual !== $expected) {
                $errors[] = "$where 应为 select，选项=".json_encode($expected).'; 实际='.json_encode($field);
            }
            if (array_key_exists('default', $source)) {
                $default = $defaultOverrides[$where] ?? certimateLocalValue($endpoint, $key, $source['default']);
                if (($field['default'] ?? null) !== $default) {
                    $errors[] = "$where 默认值应为 ".json_encode($default);
                }
            }
        }
    }
    expect($errors)->toBe([], implode("\n", $errors));
});

test('所有部署表单在上游每个选择分支下显示相同的相关字段', function () {
    $fixture = certimateFormFixture();
    $catalog = cloudDeployCertimateFixture();
    $evaluator = certimateSchemaConditionEvaluator();
    $errors = [];
    foreach (app(Registry::class)->allDeployers() as ['provider' => $provider, 'product' => $product]) {
        $endpoint = "$provider.$product";
        $slug = cloudDeployPluginSlug($catalog, $provider, $product);
        $upstreamSlug = array_search($slug, $catalog['source_slug_aliases']['sp_files'], true) ?: $slug;
        $form = $fixture['forms'][$upstreamSlug] ?? null;
        if ($form === null) {
            continue;
        }
        $schema = app(Registry::class)->resolveDeployer($provider, $product)->configSchema();
        $fields = array_column($schema, null, 'key');
        $cases = [[]];
        foreach ($form['fields'] as $key => $source) {
            if (! isset($source['options'])) {
                continue;
            }
            $key = certimateLocalField($key, $fields);
            $next = [];
            foreach ($cases as $case) {
                foreach ($source['options'] as $value) {
                    $next[] = $case + [$key => certimateLocalValue($endpoint, $key, $value)];
                }
            }
            $cases = $next;
        }
        foreach ($form['fields'] as $key => $source) {
            if (! isset($source['visible_when'])) {
                continue;
            }
            $key = certimateLocalField($key, $fields);
            if (! isset($fields[$key])) {
                if (count($source['options'] ?? []) === 1) {
                    continue;
                }
                $errors[] = "$endpoint 缺少条件字段 $key";

                continue;
            }
            $expected = ['visible_when' => certimateLocalCondition($source['visible_when'], $endpoint, $fields)];
            foreach ($cases as $case) {
                if ($evaluator->visible($expected, $schema, $case) !== $evaluator->visible($fields[$key], $schema, $case)) {
                    $errors[] = "$endpoint.$key 联动不符：".json_encode($case);
                }
            }
        }
    }
    expect($errors)->toBe([], implode("\n", $errors));
});
