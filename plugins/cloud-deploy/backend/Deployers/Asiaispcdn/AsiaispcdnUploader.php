<?php

namespace Plugins\CloudDeploy\Deployers\Asiaispcdn;

use Closure;
use Plugins\CloudDeploy\Deployers\Contracts\CertUploaderInterface;
use RuntimeException;
use Throwable;

class AsiaispcdnUploader implements CertUploaderInterface
{
    public function __construct(private Closure $factory) {}

    public function storeKind(): string
    {
        return 'asiaispcdn';
    }

    public function upload(string $certPem, string $keyPem, string $chainPem, array $credentials): string
    {
        try {
            /** @var AsiaispcdnClient $client */
            $client = ($this->factory)($credentials);
            $id = $client->call('POST', 'certificateUpload', [
                'publicKey' => rtrim($certPem).($chainPem !== '' ? "\n".trim($chainPem) : ''),
                'privateKey' => $keyPem, 'name' => 'ssl-manager-'.time(),
            ]);
            if ((! is_int($id) && ! is_string($id)) || ! ctype_digit((string) $id) || (int) $id <= 0) {
                throw new RuntimeException('未返回证书 ID');
            }

            return (string) $id;
        } catch (Throwable $e) {
            throw new RuntimeException('AsiaISP CDN 证书上传失败: '.class_basename($e));
        }
    }
}
