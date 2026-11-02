<?php

namespace Plugins\CloudDeploy\Deployers\Lecdn;

use GuzzleHttp\ClientInterface;

/**
 * LeCDN v3/v4 REST 薄客户端（账号密码登录态 + 更新证书；按角色 client/master 分支）。
 *
 * 无官方 PHP SDK，照 certimate pkg/sdk3rd/lecdn/{client,master}/{v3,v4} 用 GuzzleHttp（来自主系统 vendor）直调。
 * base：{serverUrl}/prod-api。鉴权：账号密码登录换 token，token 进 `Authorization: Bearer` 头；token
 * **不带过期缓存**（与 GoEdge 族不同——仅当 token 为空时重登）。
 *
 * 角色差异（CRITICAL）：
 *   - 登录 POST /auth/login（v4 client 为 /login）：client 端 body {email, username, password}（email 与 username 同值）；
 *     master 端 body {username, password}。响应 data.token 取 token。
 *   - 更新 PUT /certificate/{id}：v3 master 端 body 多一个 client_id 字段；client 端无。
 *   - 响应描述键：client 端 msg、master 端 message（两者都读）。
 * PEM 经 base64 编码填 ssl_pem / ssl_key。
 *
 * 响应体 `{code, message|msg, data}` —— code==200 成功。HTTP 非 2xx 或 code≠200 归一为 LecdnApiException。
 *
 * 此类是 deployer 的 makeClient('api', …) 唯一产物 —— 测试 override makeClient 返回 mock 即覆盖全部 REST 调用。
 */
class LecdnRestClient
{
    public const ROLE_CLIENT = 'client';

    public const ROLE_MASTER = 'master';

    private string $token = '';

    /**
     * @param  string  $role  self::ROLE_CLIENT | self::ROLE_MASTER
     */
    public function __construct(
        private readonly ClientInterface $http,
        private readonly string $role,
        private readonly string $username,
        private readonly string $password,
        private readonly string $version = 'v3',
        private readonly string $apiKey = '',
    ) {}

    /**
     * 更新指定证书（PUT /certificate/{id}，PEM 经 base64 编码）。
     * REF: certimate lecdn v3 UpdateCertificate
     *
     * @param  int  $certificateId  LeCDN 证书 ID
     * @param  int  $clientId  客户 ID（仅 master 角色生效，对齐 certimate 即便 0 也序列化）
     */
    public function updateCertificate(int $certificateId, string $certPem, string $keyPem, int $clientId = 0): void
    {
        $body = [
            'name' => 'certimate-'.(int) (microtime(true) * 1000),
            'description' => 'upload from Certimate',
            'type' => 'upload',
            'ssl_pem' => base64_encode($certPem),
            'ssl_key' => base64_encode($keyPem),
            'auto_renewal' => false,
        ];
        // master 角色：body 头部带 client_id（对齐 certimate，无 omitempty，即便 0 也外发）
        if ($this->role === self::ROLE_MASTER && $this->version === 'v3') {
            $body = ['client_id' => $clientId] + $body;
        }

        if ($this->version === 'v4') {
            unset($body['type']);
        }

        $this->call('PUT', "certificate/$certificateId", $body);
    }

    /**
     * 懒登录：未取 token 时 POST /auth/login 换 token（不带过期，仅空时重登）。
     */
    private function ensureToken(): void
    {
        if ($this->token !== '') {
            return;
        }

        // 角色不同登录 body 不同：client 端 email+username+password；master 端仅 username+password
        $body = $this->role === self::ROLE_MASTER
            ? ['username' => $this->username, 'password' => $this->password]
            : ['email' => $this->username, 'username' => $this->username, 'password' => $this->password];

        $path = $this->version === 'v4' && $this->role === self::ROLE_CLIENT ? 'login' : 'auth/login';
        $json = $this->call('POST', $path, $body, true);

        $data = is_array($json['data'] ?? null) ? $json['data'] : [];
        $token = is_string($data['token'] ?? null) ? $data['token'] : '';
        if ($token === '') {
            throw new LecdnApiException('AuthError', 'LeCDN 登录失败：未取得有效 token');
        }

        $this->token = $token;
    }

    /**
     * @param  array<string,mixed>  $body
     * @return array<string,mixed>
     */
    private function call(string $method, string $path, array $body, bool $isLogin = false): array
    {
        $headers = ['Content-Type' => 'application/json', 'Accept' => 'application/json'];
        if (! $isLogin) {
            if ($this->apiKey !== '') {
                $headers['Authorization'] = $this->apiKey;
            } else {
                $this->ensureToken();
                $headers['Authorization'] = 'Bearer '.$this->token;
            }
        }

        $resp = $this->http->request($method, $path, [
            'json' => $body,
            'headers' => $headers,
            'http_errors' => false,
        ]);

        $status = $resp->getStatusCode();
        $json = json_decode((string) $resp->getBody(), true);
        $json = is_array($json) ? $json : [];
        // client 端描述键 msg、master 端 message：两者都读
        $msg = '';
        foreach (['message', 'msg'] as $k) {
            if (is_string($json[$k] ?? null) && $json[$k] !== '') {
                $msg = $json[$k];
                break;
            }
        }

        if ($status < 200 || $status >= 300) {
            throw new LecdnApiException((string) $status, $msg !== '' ? $msg : "LeCDN 接口返回 HTTP $status");
        }

        if ($json !== [] && (int) ($json['code'] ?? 0) !== 200) {
            throw new LecdnApiException((string) ($json['code'] ?? 'LeCDNError'), $msg !== '' ? $msg : 'LeCDN 接口返回错误');
        }

        return $json;
    }
}
