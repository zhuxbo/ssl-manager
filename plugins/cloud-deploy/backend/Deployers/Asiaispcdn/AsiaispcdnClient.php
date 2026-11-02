<?php

namespace Plugins\CloudDeploy\Deployers\Asiaispcdn;

use GuzzleHttp\ClientInterface;
use RuntimeException;

class AsiaispcdnClient
{
    public function __construct(private ClientInterface $http, private string $accessKeyId, private string $accessKeySecret) {}

    public function call(string $method, string $action, array $params = []): mixed
    {
        $query = ['action' => $action];
        if ($method === 'GET') {
            $query += $params;
        }
        ksort($query);
        $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC1738);
        $body = $method === 'GET' ? '' : json_encode($params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $nonce = random_int(10000000, 99999999);
        $timestamp = (int) (microtime(true) * 1000);
        $payload = $method === 'GET' ? '' : '&body='.$body;
        $canonical = "accessKeySecret={$this->accessKeySecret}{$payload}&method=$method&nonce=$nonce&queryString=$queryString&timestamp=$timestamp&uri=/openapi/v3/stat";
        $signature = rtrim(strtr(base64_encode(hash_hmac('sha1', $canonical, $this->accessKeySecret, true)), '+/', '-_'), '=');
        $options = ['headers' => [
            'Accept' => 'application/json', 'Content-Type' => 'application/json',
            'accessKeyId' => $this->accessKeyId, 'nonce' => (string) $nonce, 'timestamp' => (string) $timestamp, 'signature' => $signature,
        ], 'http_errors' => false];
        if ($method !== 'GET') {
            $options['body'] = $body;
        }
        $response = $this->http->request($method, 'https://api.asia-isp.com/openapi/v3/stat?'.$queryString, $options);
        $data = json_decode((string) $response->getBody(), true);
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300 || ! is_array($data) || ($data['code'] ?? null) !== 0) {
            throw new RuntimeException('AsiaISP CDN 接口调用失败');
        }

        return $data['data'] ?? null;
    }
}
