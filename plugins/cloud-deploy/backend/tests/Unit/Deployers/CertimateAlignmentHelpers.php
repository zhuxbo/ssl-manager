<?php

function cloudDeployCertimateFixture(): array
{
    $json = file_get_contents(dirname(__DIR__, 2).'/Fixtures/certimate-deployer-catalog.json');
    expect($json)->not->toBeFalse('Certimate fixture should exist');

    $fixture = json_decode((string) $json, true);
    expect($fixture)->toBeArray('Certimate fixture should be valid JSON');

    return $fixture;
}

function cloudDeploySupportedCoreSlugs(array $fixture): array
{
    $unsupported = $fixture['unsupported'] ?? [];
    $slugs = array_values(array_diff($fixture['core_dirs'], $unsupported));
    sort($slugs);

    return $slugs;
}

function cloudDeployPluginSlug(array $fixture, string $provider, string $product): string
{
    $key = "$provider.$product";
    if (isset($fixture['plugin_slug_aliases'][$key])) {
        return $fixture['plugin_slug_aliases'][$key];
    }

    $provider = $fixture['provider_aliases'][$provider] ?? $provider;

    return "$provider-$product";
}

function cloudDeployNormalizeSourceSlugs(array $values, array $aliases, array $unsupported): array
{
    $normalized = [];
    foreach ($values as $value) {
        $slug = $aliases[$value] ?? $value;
        if (in_array($slug, $unsupported, true)) {
            continue;
        }
        $normalized[] = $slug;
    }
    sort($normalized);

    return array_values(array_unique($normalized));
}
