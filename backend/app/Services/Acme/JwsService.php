<?php

declare(strict_types=1);

namespace App\Services\Acme;

use App\Models\Acme\AcmeAccount;

class JwsService
{
    /**
     * 解析 JWS 请求
     */
    public function parse(string $body): ?array
    {
        $data = json_decode($body, true);

        if (!$data || !isset($data['protected'], $data['payload'], $data['signature'])) {
            return null;
        }

        $protected = $this->base64UrlDecode($data['protected']);
        $payload = $data['payload'] ? $this->base64UrlDecode($data['payload']) : '';

        $protectedHeader = json_decode($protected, true);
        $payloadData = $payload ? json_decode($payload, true) : [];

        return [
            'protected' => $protectedHeader,
            'payload' => $payloadData,
            'signature' => $data['signature'],
            'raw_protected' => $data['protected'],
            'raw_payload' => $data['payload'],
        ];
    }

    /**
     * 验证 JWS 签名
     */
    public function verify(array $jws, array $publicKey): bool
    {
        $signingInput = $jws['raw_protected'] . '.' . $jws['raw_payload'];
        $signature = $this->base64UrlDecode($jws['signature']);

        // 从 JWK 构建 PEM 公钥
        $pem = $this->jwkToPem($publicKey);

        if (!$pem) {
            return false;
        }

        $alg = $jws['protected']['alg'] ?? '';
        $kty = $publicKey['kty'] ?? '';

        // 验证算法与密钥类型匹配，防止算法混淆攻击
        $algorithm = $this->validateAndGetAlgorithm($alg, $kty, $publicKey);

        if ($algorithm === null) {
            return false;
        }

        $publicKeyResource = openssl_pkey_get_public($pem);

        if (!$publicKeyResource) {
            return false;
        }

        return openssl_verify($signingInput, $signature, $publicKeyResource, $algorithm) === 1;
    }

    /**
     * 验证算法与密钥类型匹配，返回 OpenSSL 算法常量
     */
    private function validateAndGetAlgorithm(string $alg, string $kty, array $publicKey): ?int
    {
        // RSA 密钥只允许 RS256/384/512
        if ($kty === 'RSA') {
            return match ($alg) {
                'RS256' => OPENSSL_ALGO_SHA256,
                'RS384' => OPENSSL_ALGO_SHA384,
                'RS512' => OPENSSL_ALGO_SHA512,
                default => null, // 拒绝不匹配的算法
            };
        }

        // EC 密钥只允许 ES256/384/512，且需验证曲线匹配
        if ($kty === 'EC') {
            $crv = $publicKey['crv'] ?? '';

            return match (true) {
                $alg === 'ES256' && $crv === 'P-256' => OPENSSL_ALGO_SHA256,
                $alg === 'ES384' && $crv === 'P-384' => OPENSSL_ALGO_SHA384,
                $alg === 'ES512' && $crv === 'P-521' => OPENSSL_ALGO_SHA512,
                default => null, // 拒绝不匹配的算法或曲线
            };
        }

        return null; // 未知的密钥类型
    }

    /**
     * 从 JWS 头部提取公钥
     */
    public function extractPublicKey(array $protected): ?array
    {
        return $protected['jwk'] ?? null;
    }

    /**
     * 从 JWS 头部提取 kid
     */
    public function extractKid(array $protected): ?string
    {
        return $protected['kid'] ?? null;
    }

    /**
     * 计算 JWK 指纹作为 key_id
     */
    public function computeKeyId(array $jwk): string
    {
        // 按字母顺序排列必需的参数
        $thumbprintInput = match ($jwk['kty']) {
            'RSA' => json_encode([
                'e' => $jwk['e'],
                'kty' => $jwk['kty'],
                'n' => $jwk['n'],
            ], JSON_UNESCAPED_SLASHES),
            'EC' => json_encode([
                'crv' => $jwk['crv'],
                'kty' => $jwk['kty'],
                'x' => $jwk['x'],
                'y' => $jwk['y'],
            ], JSON_UNESCAPED_SLASHES),
            default => '',
        };

        return $this->base64UrlEncode(hash('sha256', $thumbprintInput, true));
    }

    /**
     * 验证 EAB（外部账户绑定）
     */
    public function verifyEab(array $outerJws, string $eabKid, string $eabHmac): bool
    {
        $payload = $outerJws['payload'] ?? [];
        $eab = $payload['externalAccountBinding'] ?? null;

        if (!$eab) {
            return false;
        }

        // 解析内部 JWS
        $innerProtected = json_decode($this->base64UrlDecode($eab['protected']), true);

        // 验证 kid 匹配
        if (($innerProtected['kid'] ?? '') !== $eabKid) {
            return false;
        }

        // 验证 HMAC 签名
        $signingInput = $eab['protected'] . '.' . $eab['payload'];
        $hmacKey = $this->base64UrlDecode($eabHmac);
        $expectedSignature = hash_hmac('sha256', $signingInput, $hmacKey, true);

        return hash_equals($this->base64UrlDecode($eab['signature']), $expectedSignature);
    }

    /**
     * 根据账户 URL 查找账户
     */
    public function findAccountByKid(string $kid): ?AcmeAccount
    {
        // kid 格式: https://manager.example.com/acme/acct/{key_id}
        if (preg_match('/\/acme\/acct\/([^\/]+)$/', $kid, $matches)) {
            return AcmeAccount::where('key_id', $matches[1])->first();
        }

        return null;
    }

    /**
     * Base64 URL 安全编码
     */
    public function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL 安全解码
     */
    public function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * JWK 转 PEM 格式公钥
     */
    private function jwkToPem(array $jwk): ?string
    {
        if ($jwk['kty'] === 'RSA') {
            return $this->rsaJwkToPem($jwk);
        }

        if ($jwk['kty'] === 'EC') {
            return $this->ecJwkToPem($jwk);
        }

        return null;
    }

    /**
     * RSA JWK 转 PEM
     */
    private function rsaJwkToPem(array $jwk): string
    {
        $n = $this->base64UrlDecode($jwk['n']);
        $e = $this->base64UrlDecode($jwk['e']);

        // 构建 ASN.1 DER 编码
        $modulus = $this->encodeAsn1Integer($n);
        $exponent = $this->encodeAsn1Integer($e);

        $rsaPublicKey = chr(0x30) . $this->encodeAsn1Length(strlen($modulus) + strlen($exponent)) . $modulus . $exponent;

        // OID for rsaEncryption
        $oid = chr(0x30) . chr(0x0d) . chr(0x06) . chr(0x09) . hex2bin('2a864886f70d010101') . chr(0x05) . chr(0x00);

        $bitString = chr(0x03) . $this->encodeAsn1Length(strlen($rsaPublicKey) + 1) . chr(0x00) . $rsaPublicKey;

        $der = chr(0x30) . $this->encodeAsn1Length(strlen($oid) + strlen($bitString)) . $oid . $bitString;

        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----";
    }

    /**
     * EC JWK 转 PEM
     */
    private function ecJwkToPem(array $jwk): string
    {
        $x = $this->base64UrlDecode($jwk['x']);
        $y = $this->base64UrlDecode($jwk['y']);

        // 确定曲线
        $oid = match ($jwk['crv']) {
            'P-256' => hex2bin('2a8648ce3d030107'),
            'P-384' => hex2bin('2b81040022'),
            'P-521' => hex2bin('2b81040023'),
            default => hex2bin('2a8648ce3d030107'),
        };

        $point = chr(0x04) . $x . $y;

        // 构建 ASN.1 结构
        $ecOid = chr(0x06) . chr(0x07) . hex2bin('2a8648ce3d0201'); // ecPublicKey
        $curveOid = chr(0x06) . chr(strlen($oid)) . $oid;

        $algorithmIdentifier = chr(0x30) . $this->encodeAsn1Length(strlen($ecOid) + strlen($curveOid)) . $ecOid . $curveOid;

        $bitString = chr(0x03) . $this->encodeAsn1Length(strlen($point) + 1) . chr(0x00) . $point;

        $der = chr(0x30) . $this->encodeAsn1Length(strlen($algorithmIdentifier) + strlen($bitString)) . $algorithmIdentifier . $bitString;

        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----";
    }

    /**
     * ASN.1 整数编码
     */
    private function encodeAsn1Integer(string $data): string
    {
        // 空字符串返回 ASN.1 编码的 0
        if ($data === '') {
            return chr(0x02) . chr(0x01) . chr(0x00);
        }

        // 如果最高位是1，需要添加前导0
        if (ord($data[0]) & 0x80) {
            $data = chr(0x00) . $data;
        }

        return chr(0x02) . $this->encodeAsn1Length(strlen($data)) . $data;
    }

    /**
     * ASN.1 长度编码
     */
    private function encodeAsn1Length(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }

        $bytes = '';
        while ($length > 0) {
            $bytes = chr($length & 0xff) . $bytes;
            $length >>= 8;
        }

        return chr(0x80 | strlen($bytes)) . $bytes;
    }
}
