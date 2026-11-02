<?php

namespace Plugins\CloudDeploy\Deployers\Asiaispcdn;

use Plugins\CloudDeploy\Deployers\Contracts\ProviderInterface;

class AsiaispcdnProvider implements ProviderInterface
{
    public function key(): string
    {
        return 'asiaispcdn';
    }

    public function label(): string
    {
        return '橙域网络 CDN';
    }

    public function credentialSchema(): array
    {
        return [
            ['key' => 'access_key_id', 'label' => 'AccessKey ID', 'required' => true],
            ['key' => 'access_key_secret', 'label' => 'AccessKey Secret', 'required' => true, 'secret' => true],
        ];
    }
}
