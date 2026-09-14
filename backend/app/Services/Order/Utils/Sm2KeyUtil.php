<?php

declare(strict_types=1);

namespace App\Services\Order\Utils;

use App\Services\Binary\BinaryLocator;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class Sm2KeyUtil
{
    public static function decrypt(#[\SensitiveParameter] string $envelope, #[\SensitiveParameter] string $privateKey, string $certificate): string
    {
        $keyFile = null;
        try {
            $der = base64_decode(preg_replace('/\s+/', '', $envelope) ?? '', true);
            if ($der === false || strlen($der) > 4096) {
                throw new RuntimeException;
            }
            $body = self::take($der, 0x30);
            self::assertEmpty($der);
            $algorithm = self::take($body, 0x30);
            // GM/T 0009 的 SM4-ECB 密钥信封，仅接受已支持的算法。
            if (self::take($algorithm, 0x06) !== hex2bin('2a811ccf55016801') || ! in_array($algorithm, ['', "\x05\x00"], true)) {
                throw new RuntimeException;
            }
            $cipher = self::wrap(0x30, self::take($body, 0x30));
            $publicKey = self::take($body, 0x03);
            $encryptedKey = self::take($body, 0x03);
            self::assertEmpty($body);
            if (strlen($publicKey) !== 66 || substr($publicKey, 0, 2) !== "\x00\x04" || strlen($encryptedKey) !== 33 || $encryptedKey[0] !== "\x00") {
                throw new RuntimeException;
            }

            $openssl = app(BinaryLocator::class)->gmOpenssl();
            $keyFile = tempnam(sys_get_temp_dir(), 'sm2-key-');
            if ($keyFile === false || ! chmod($keyFile, 0600) || file_put_contents($keyFile, $privateKey) !== strlen($privateKey)) {
                throw new RuntimeException;
            }
            $sessionKey = self::run([$openssl, 'pkeyutl', '-decrypt', '-inkey', $keyFile], $cipher);
            if (strlen($sessionKey) !== 16) {
                throw new RuntimeException;
            }
            $scalar = openssl_decrypt(substr($encryptedKey, 1), 'sm4-ecb', $sessionKey, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);
            if ($scalar === false || strlen($scalar) !== 32) {
                throw new RuntimeException;
            }
            // SEC1 私钥不携带公钥，由 OpenSSL 从私钥推导，避免仅比较信封自报的公钥。
            $keyDer = self::wrap(0x30, "\x02\x01\x01".self::wrap(0x04, $scalar).self::wrap(0xA0, self::wrap(0x06, hex2bin('2a811ccf5501822d'))));
            // PKCS#8 显式声明 EC 算法和 SM2 曲线，兼容 OpenSSL 3.0.13 导入无公钥的私钥。
            $keyDer = self::wrap(0x30, "\x02\x01\x00".hex2bin('301306072a8648ce3d020106082a811ccf5501822d').self::wrap(0x04, $keyDer));
            $keyPem = "-----BEGIN PRIVATE KEY-----\n".chunk_split(base64_encode($keyDer), 64, "\n")."-----END PRIVATE KEY-----\n";
            $derivedPublic = self::run([$openssl, 'pkey', '-pubout', '-outform', 'DER'], $keyPem);
            $certPublic = self::run([$openssl, 'x509', '-pubkey', '-noout'], $certificate);
            $certPublicDer = self::run([$openssl, 'pkey', '-pubin', '-outform', 'DER'], $certPublic);
            if (! hash_equals($derivedPublic, $certPublicDer) || ! hash_equals(substr($publicKey, 1), substr($derivedPublic, -65))) {
                throw new RuntimeException;
            }

            return self::run([$openssl, 'pkey'], $keyPem);
        } catch (Throwable) {
            // 不透传进程输出、密钥或底层异常链。
            throw new RuntimeException('国密加密私钥解密失败，请检查用户私钥、GMT-0009 文件与加密证书是否匹配');
        } finally {
            if (is_string($keyFile)) {
                @unlink($keyFile);
            }
        }
    }

    /** @param list<string> $command */
    private static function run(array $command, #[\SensitiveParameter] string $input): string
    {
        $process = new Process($command);
        $process->setTimeout(15);
        $process->setInput($input);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new RuntimeException;
        }

        return $process->getOutput();
    }

    private static function take(#[\SensitiveParameter] string &$data, int $tag): string
    {
        if (strlen($data) < 2 || ord($data[0]) !== $tag) {
            throw new RuntimeException;
        }
        $length = ord($data[1]);
        $header = 2;
        if ($length & 0x80) {
            $count = $length & 0x7F;
            if ($count < 1 || $count > 2 || strlen($data) < 2 + $count || $data[2] === "\x00") {
                throw new RuntimeException;
            }
            $length = 0;
            for ($i = 0; $i < $count; $i++) {
                $length = ($length << 8) | ord($data[2 + $i]);
            }
            if ($length < 128) {
                throw new RuntimeException;
            }
            $header += $count;
        }
        if ($length > strlen($data) - $header) {
            throw new RuntimeException;
        }
        $value = substr($data, $header, $length);
        $data = substr($data, $header + $length);

        return $value;
    }

    private static function assertEmpty(#[\SensitiveParameter] string $data): void
    {
        if ($data !== '') {
            throw new RuntimeException;
        }
    }

    private static function wrap(int $tag, #[\SensitiveParameter] string $value): string
    {
        $length = strlen($value);

        return chr($tag).($length < 128 ? chr($length) : "\x81".chr($length)).$value;
    }
}
