<?php

use App\Exceptions\ApiResponseException;
use App\Models\Cert;
use App\Models\Order;
use App\Services\Binary\BinaryLocator;
use App\Services\Order\Action;
use App\Services\Order\Utils\Sm2KeyUtil;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

function sm2ZipNames(string $path): array
{
    $zip = new ZipArchive;
    $zip->open($path);
    $names = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $names[] = $zip->getNameIndex($i);
    }
    $zip->close();

    return $names;
}

function sm2TestMaterial(): array
{
    $openssl = app(BinaryLocator::class)->gmOpenssl();
    $run = function (array $args, string $input = '') use ($openssl): string {
        $process = new Process([$openssl, ...$args]);
        $process->setInput($input);
        $process->mustRun();

        return $process->getOutput();
    };
    $signKey = $run(['genpkey', '-algorithm', 'SM2']);
    $encKey = $run(['genpkey', '-algorithm', 'SM2']);
    $makeCert = fn ($key) => $run(['req', '-new', '-x509', '-key', '/dev/stdin', '-subj', '/CN=sm2.example.com', '-days', '1', '-sm3', '-sigopt', 'distid:1234567812345678'], $key);
    $encCert = $makeCert($encKey);
    $encDer = $run(['pkey', '-traditional', '-outform', 'DER'], $encKey);
    // OpenSSL SEC1: SEQUENCE + version INTEGER + 32-byte privateKey OCTET STRING.
    $scalar = substr($encDer, 7, 32);
    $public = substr($run(['pkey', '-pubout', '-outform', 'DER'], $encKey), -65);
    $sessionKey = random_bytes(16);
    $pubFile = tempnam(sys_get_temp_dir(), 'sm2-test-');
    try {
        file_put_contents($pubFile, $run(['pkey', '-pubout'], $signKey));
        $cipher = $run(['pkeyutl', '-encrypt', '-pubin', '-inkey', $pubFile], $sessionKey);
    } finally {
        unlink($pubFile);
    }
    $wrap = fn ($tag, $data) => chr($tag).(strlen($data) < 128 ? chr(strlen($data)) : "\x81".chr(strlen($data))).$data;
    $envelope = $wrap(0x30, hex2bin('300a06082a811ccf55016801').$cipher.$wrap(3, "\0".$public).$wrap(3, "\0".openssl_encrypt($scalar, 'sm4-ecb', $sessionKey, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING)));

    return [
        'common_name' => 'sm2.example.com',
        'encryption_alg' => 'SM2',
        'cert' => $makeCert($signKey),
        'private_key' => $signKey,
        'enc_cert' => $encCert,
        'enc_key2' => base64_encode($envelope),
    ];
}

test('国密下载解密 GMT-0009 并输出五个部署文件，无需 GMT-0016', function () {
    $material = sm2TestMaterial();
    $cert = new Cert($material);
    $order = new Order;
    $order->setRelation('latestCert', $cert);

    $zipPath = tempnam(sys_get_temp_dir(), 'gmzip');
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::OVERWRITE);
    $reflect = new ReflectionMethod(Action::class, 'addCertToZip');
    $reflect->invoke(app(Action::class), $order, $zip, sys_get_temp_dir(), [], 'all');
    $zip->close();

    $zip->open($zipPath);
    $base = 'sm2.example.com/nginx/';
    $key = $zip->getFromName($base.'encert.key');
    expect($key)->toStartWith('-----BEGIN PRIVATE KEY-----');
    expect($zip->getFromName($base.'usercert.key'))->toBe($material['private_key']);
    expect($zip->getFromName($base.'encert.crt'))->toBe(trim($material['enc_cert']));
    expect(explode("\r\n", $zip->getFromName($base.'说明.txt')))->toBe([
        'usercert.crt 用户证书',
        'usercert.key 用户私钥，与用户证书匹配',
        'encert.crt 用户加密证书',
        'encert.key 用户加密私钥，与用户加密证书匹配',
    ]);
    $zip->close();
    $names = sm2ZipNames($zipPath);
    @unlink($zipPath);
    expect(array_filter($names, fn ($name) => str_starts_with($name, $base)))->toHaveCount(5);
    foreach (['usercert.crt', 'usercert.key', 'encert.crt', 'encert.key', '说明.txt'] as $name) {
        expect($names)->toContain($base.$name);
    }
});

test('缺少 GMT-0009 时仅出签名部分，不输出密文冒充私钥', function () {
    $cert = new Cert([
        'common_name' => 'sm2b.example.com',
        'encryption_alg' => 'SM2',
        'cert' => "-----BEGIN CERTIFICATE-----\nSIGN\n-----END CERTIFICATE-----",
        'enc_cert' => "-----BEGIN CERTIFICATE-----\nENC\n-----END CERTIFICATE-----",
        'enc_key' => 'GMT0016-IS-NOT-A-DEPLOYMENT-KEY',
        'private_key' => 'SIGN-KEY',
        // 缺少 enc_key2，即使有 GMT-0016 仍不能生成加密私钥
    ]);
    $order = new Order;
    $order->setRelation('latestCert', $cert);

    $zipPath = tempnam(sys_get_temp_dir(), 'gmzip');
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::OVERWRITE);
    $reflect = new ReflectionMethod(Action::class, 'addCertToZip');
    $reflect->invoke(app(Action::class), $order, $zip, sys_get_temp_dir(), [], 'all');
    $zip->close();

    $names = sm2ZipNames($zipPath);
    @unlink($zipPath);

    $base = 'sm2b.example.com/nginx/';
    expect($names)->toContain($base.'usercert.crt');
    // 缺少 GMT-0009，不得把 GMT-0016 密文作为部署私钥。
    expect(collect($names)->contains(fn ($n) => str_contains($n, '/nginx/encert.')))->toBeFalse();
    expect($names)->toContain('sm2b.example.com/nginx/说明.txt');
});

test('非国密证书（enc_cert 空）走普通格式分支，不出国密双证书文件', function () {
    $cert = new Cert([
        'common_name' => 'normal.example.com',
        'cert' => "-----BEGIN CERTIFICATE-----\nX\n-----END CERTIFICATE-----",
        // 无 enc_cert
    ]);
    $order = new Order;
    $order->setRelation('latestCert', $cert);

    $zipPath = tempnam(sys_get_temp_dir(), 'normzip');
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::OVERWRITE);
    $reflect = new ReflectionMethod(Action::class, 'addCertToZip');
    $reflect->invoke(app(Action::class), $order, $zip, sys_get_temp_dir(), [], 'nginx');
    $zip->close();

    $names = sm2ZipNames($zipPath);
    @unlink($zipPath);

    // 普通 nginx 包，无国密 _sign/_enc 文件
    expect(collect($names)->contains(fn ($n) => str_contains($n, '_enc.crt') || str_contains($n, '_sign.crt')))->toBeFalse();
    expect($names)->toContain('normal.example.com/nginx/normal.example.com.crt');
});

test('encryption_alg=SM2 但 enc_cert 空（gateway 未就绪）仍强制国密 nginx 包：仅签名、不丢私钥、不出 apache/iis', function () {
    // 杀手场景：上游已签发签名证书（encryption_alg=SM2），但 CA/KGC 加密证书未就绪 → enc_cert 空。
    // 旧实现用 enc_cert 非空判定国密，会让此证书掉进普通格式分支：
    //   - openssl_x509_check_private_key 对 SM2 返回 false → 签名私钥 _sign.key 丢失
    //   - type=all 还会打出 apache/pem/iis 等无意义格式
    // 修复后按 encryption_alg 判定，强制走国密 nginx 分支、降级仅出签名。
    $cert = new Cert([
        'common_name' => 'sm2c.example.com',
        'encryption_alg' => 'SM2',
        'cert' => "-----BEGIN CERTIFICATE-----\nSIGN\n-----END CERTIFICATE-----",
        'private_key' => 'SIGN-KEY',
        // enc_cert / enc_key / enc_key2 全空：CA/KGC 未下发加密证书
    ]);
    $order = new Order;
    $order->setRelation('latestCert', $cert);

    $zipPath = tempnam(sys_get_temp_dir(), 'gmzip');
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::OVERWRITE);
    $reflect = new ReflectionMethod(Action::class, 'addCertToZip');
    // 用 type=all 触发普通逻辑的全部格式分支，验证国密判定能拦截在前
    $reflect->invoke(app(Action::class), $order, $zip, sys_get_temp_dir(), [], 'all');
    $zip->close();

    $names = sm2ZipNames($zipPath);
    @unlink($zipPath);

    $base = 'sm2c.example.com/nginx/';
    // 签名证书 + 签名私钥必须在（不因 PHP openssl 不支持 SM2 而丢失）
    expect($names)->toContain($base.'usercert.crt');
    expect($names)->toContain($base.'usercert.key');
    expect($names)->toContain('sm2c.example.com/nginx/说明.txt');
    // 加密证书未就绪：不写任何空的 _enc 文件
    expect(collect($names)->contains(fn ($n) => str_contains($n, '/nginx/encert.')))->toBeFalse();
    // 仍强制国密：不出普通 apache/iis/tomcat/pem 格式
    expect(collect($names)->contains(fn ($n) => str_contains($n, 'apache/') || str_contains($n, 'iis/') || str_contains($n, 'tomcat/') || str_contains($n, 'pem/')))->toBeFalse();
    // 也不出普通 nginx 单证书文件（普通分支的 .crt 而非国密 _sign.crt）
    expect($names)->not->toContain('sm2c.example.com/nginx/sm2c.example.com.crt');
});

test('GMT-0009 解密拒绝错误私钥、证书不匹配及损坏信封', function () {
    $material = sm2TestMaterial();
    $other = sm2TestMaterial();
    foreach ([
        [$material['enc_key2'], $other['private_key'], $material['enc_cert']],
        [$material['enc_key2'], $material['private_key'], $other['enc_cert']],
        [base64_encode(base64_decode($material['enc_key2'])."\0"), $material['private_key'], $material['enc_cert']],
        ['invalid', $material['private_key'], $material['enc_cert']],
    ] as $args) {
        expect(fn () => Sm2KeyUtil::decrypt(...$args))
            ->toThrow(RuntimeException::class, '国密加密私钥解密失败');
    }
});

test('GMT-0009 解密失败时打包报错且不写入部署文件', function () {
    $material = sm2TestMaterial();
    $material['enc_key2'] = 'broken-envelope';
    $order = new Order;
    $order->setRelation('latestCert', new Cert($material));
    $zipPath = tempnam(sys_get_temp_dir(), 'gmzip');
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::OVERWRITE);
    try {
        $method = new ReflectionMethod(Action::class, 'addSm2CertToZip');
        expect(fn () => $method->invoke(app(Action::class), $zip, 'test/', $material['cert'], $material['private_key'], '', $material['enc_cert'], $material['enc_key2'], ''))
            ->toThrow(ApiResponseException::class);
        expect($zip->numFiles)->toBe(0);
    } finally {
        $zip->close();
        @unlink($zipPath);
    }
});

test('自带 CSR 无签名私钥时交付证书和原始 GMT-0009，用户可用原私钥本地解密', function () {
    $material = sm2TestMaterial();
    $privateKey = $material['private_key'];
    unset($material['private_key']);
    $order = new Order;
    $order->setRelation('latestCert', new Cert($material));
    $zipPath = tempnam(sys_get_temp_dir(), 'gmzip');
    $workDir = sys_get_temp_dir().'/gm-work-'.bin2hex(random_bytes(8));
    mkdir($workDir, 0700);
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::OVERWRITE);
    try {
        $method = new ReflectionMethod(Action::class, 'addCertToZip');
        $method->invoke(app(Action::class), $order, $zip, $workDir, [], 'all');
        $zip->close();
        $zip->open($zipPath);
        $base = 'sm2.example.com/nginx/';
        expect(array_filter(sm2ZipNames($zipPath), fn ($name) => str_starts_with($name, $base)))->toHaveCount(4);
        expect($zip->getFromName('sm2.example.com/original/usercert.key'))->toBeFalse();
        expect($zip->getFromName('sm2.example.com/original/encert_gmt0009.key'))->toBe($material['enc_key2']);
        expect($zip->getFromName($base.'usercert.crt'))->toBe(trim($material['cert']));
        expect($zip->getFromName($base.'encert.crt'))->toBe(trim($material['enc_cert']));
        expect($zip->getFromName($base.'usercert.key'))->toBeFalse();
        expect($zip->getFromName($base.'encert.key'))->toBeFalse();
        $envelope = $zip->getFromName($base.'encert_gmt0009.key');
        expect($envelope)->toBe($material['enc_key2']);
        expect(explode("\r\n", $zip->getFromName($base.'说明.txt')))->toBe([
            'usercert.crt 用户证书',
            'encert.crt 用户加密证书',
            'encert_gmt0009.key GMT-0009 密钥信封，不能直接作为部署私钥使用',
            '',
            '系统未保存用户私钥（例如使用自带 CSR 申请）。请使用生成该 CSR 时保留的签名私钥作为 usercert.key，并在本地用它解密 encert_gmt0009.key，得到与 encert.crt 匹配的 encert.key 后再部署。',
            '无法从 CSR 或证书恢复用户私钥；如原私钥已丢失，请重新生成密钥和 CSR 后申请重签。',
        ]);
        expect(Sm2KeyUtil::decrypt($envelope, $privateKey, $zip->getFromName($base.'encert.crt')))
            ->toStartWith('-----BEGIN PRIVATE KEY-----');
    } finally {
        $zip->close();
        @unlink($zipPath);
        File::deleteDirectory($workDir);
    }
});

test('国密原始目录逐字保留已有材料，两张部署证书均拼接 CA 链', function (bool $hasPrivateKey) {
    $material = sm2TestMaterial();
    $privateKey = $hasPrivateKey ? $material['private_key'] : '';
    $chain = "CA-CHAIN-RAW\r\n";
    $gmt0016 = "GMT0016-RAW\r\n";
    $zipPath = tempnam(sys_get_temp_dir(), 'gmzip');
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::OVERWRITE);
    try {
        $method = new ReflectionMethod(Action::class, 'addSm2CertToZip');
        $method->invoke(app(Action::class), $zip, 'test/', $material['cert'], $privateKey, $chain, $material['enc_cert'], $material['enc_key2'], $gmt0016);
        $zip->close();
        $zip->open($zipPath);
        foreach ([
            'usercert.crt' => $material['cert'],
            'ca.crt' => $chain,
            'encert.crt' => $material['enc_cert'],
            'encert_gmt0009.key' => $material['enc_key2'],
            'encert_gmt0016.key' => $gmt0016,
        ] as $name => $contents) {
            expect($zip->getFromName('test/original/'.$name))->toBe($contents);
        }
        expect($zip->getFromName('test/original/usercert.key'))->toBe($hasPrivateKey ? $privateKey : false);
        expect($zip->getFromName('test/original/说明.txt'))->toContain('GMT-0009', 'GMT-0016', 'CA 证书链', '不能直接作为部署私钥');
        expect($zip->getFromName('test/nginx/usercert.crt'))->toBe(trim($material['cert'])."\n".trim($chain)."\n");
        expect($zip->getFromName('test/nginx/encert.crt'))->toBe(trim($material['enc_cert'])."\n".trim($chain)."\n");
    } finally {
        $zip->close();
        @unlink($zipPath);
    }
})->with([true, false]);
