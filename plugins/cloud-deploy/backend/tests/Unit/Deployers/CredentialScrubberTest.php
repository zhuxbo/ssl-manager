<?php

use AlibabaCloud\Oss\V2\Exception\ServiceException;
use AlibabaCloud\Tea\Exception\TeaError;
use Darabonba\OpenApi\Exceptions\ClientException;
use Plugins\CloudDeploy\Deployers\Aliyun\AliyunErrorSanitizer;
use Plugins\CloudDeploy\Deployers\Contracts\CredentialScrubber;
use Plugins\CloudDeploy\Deployers\Tencent\TencentErrorSanitizer;
use TencentCloud\Common\Exception\TencentCloudSDKException;
use Tests\TestCase;

uses(TestCase::class);

test('阿里错误码提取仅接受各 SDK 的结构化服务端错误', function (Closure $make, ?string $expected) {
    expect(AliyunErrorSanitizer::errorCode($make()))->toBe($expected);
})->with([
    'openapi-core' => [fn () => new ClientException([
        'statusCode' => 400,
        'code' => 'InvalidArgument',
        'message' => 'code: 400, invalid argument',
        'description' => '',
        'data' => ['Code' => 'InvalidArgument', 'Message' => 'invalid argument'],
        'accessDeniedDetail' => [],
        'requestId' => 'req-openapi',
    ]), 'InvalidArgument'],
    'Tea 结构化错误' => [fn () => new TeaError([
        'code' => 'InvalidArgument',
        'message' => 'invalid argument',
        'data' => ['Code' => 'InvalidArgument', 'Message' => 'invalid argument'],
    ]), 'InvalidArgument'],
    'OSS 服务端错误' => [fn () => new ServiceException([
        'status_code' => 403,
        'code' => 'AccessDenied',
        'message' => 'permission denied',
        'request_id' => 'req-oss',
    ]), 'AccessDenied'],
    '未知异常' => [fn () => new RuntimeException('private key has to be in PEM format'), null],
]);

/**
 * 加固 1 — sanitizer 凭证子串兜底扫描（纵深防御）验证：
 *   ① CredentialScrubber::scrub 对各凭证 pattern 命中即 redact；普通文案不误伤。
 *   ② **关键**：构造一个 sanitizer「正常分支会透传」但含凭证的输入，断言经 sanitize() 出口被兜底 redact
 *      —— 证明兜底不是装饰，确实在威胁模型边界被破时拦下凭证。
 */
test('scrub 命中阿里 AccessKeyId 字面量（AKIA + 16 位）', function () {
    expect(CredentialScrubber::scrub('id is AKIAEXAMPLE123456789 here'))
        ->toContain('[redacted]')
        ->not->toContain('AKIAEXAMPLE123456789');
});

test('scrub 命中阿里云 LTAI 前缀 AccessKeyId', function () {
    expect(CredentialScrubber::scrub('ak=LTAI5tFakeKeyId00000'))
        ->toContain('[redacted]')
        ->not->toContain('LTAI5tFakeKeyId00000');
});

test('scrub 命中签名查询串 AccessKeyId= / Signature=（连值一并抹）', function () {
    $in = 'https://x.aliyuncs.com/?AccessKeyId=AKIAEXAMPLE123456789&Signature=wJalrXUtnFEMIbcdEXAMPLEKEY&Action=Foo';
    $out = CredentialScrubber::scrub($in);

    expect($out)
        ->not->toContain('AKIAEXAMPLE123456789')
        ->not->toContain('wJalrXUtnFEMIbcdEXAMPLEKEY')
        ->not->toContain('AccessKeyId=')
        ->not->toContain('Signature=');
});

test('scrub 命中 OSSAccessKeyId= 与 AccessKeySecret=', function () {
    $out = CredentialScrubber::scrub('OSSAccessKeyId=AKIAEXAMPLE123456789&x=1 AccessKeySecret=topsecretvalue');

    expect($out)
        ->not->toContain('AKIAEXAMPLE123456789')
        ->not->toContain('topsecretvalue')
        ->not->toContain('OSSAccessKeyId=')
        ->not->toContain('AccessKeySecret=');
});

test('scrub 命中腾讯 secret_id / secret_key（下划线写法，连值抹）', function () {
    $out = CredentialScrubber::scrub('cred secret_id=AKIDz8krbsJ5yKBZQpnEXAMPLE secret_key=wJalrXUtnFEMIK7MDENGEXAMPLEKEY tail');

    expect($out)
        ->not->toContain('AKIDz8krbsJ5yKBZQpnEXAMPLE')
        ->not->toContain('wJalrXUtnFEMIK7MDENGEXAMPLEKEY')
        ->not->toContain('secret_id=')
        ->not->toContain('secret_key=');
});

test('scrub 命中 SecretId / SecretKey（驼峰写法）', function () {
    $out = CredentialScrubber::scrub('SecretId: AKIDz8krbsJ5yKBZQpnEXAMPLE, SecretKey: wJalrXUtnFEMIK7MDENGEXAMPLEKEY');

    expect($out)
        ->not->toContain('AKIDz8krbsJ5yKBZQpnEXAMPLE')
        ->not->toContain('wJalrXUtnFEMIK7MDENGEXAMPLEKEY');
});

test('scrub 命中 PEM 私钥头', function () {
    $out = CredentialScrubber::scrub("error body: -----BEGIN PRIVATE KEY-----\nMIIEvQ\n...");

    expect($out)
        ->toContain('[redacted]')
        ->not->toContain('-----BEGIN');
});

test('scrub 不误伤普通错误文案（无凭证 pattern 原样返回）', function () {
    $msg = '[InvalidDomain.NotFound] 域名不存在 request id: req-123';
    expect(CredentialScrubber::scrub($msg))->toBe($msg);
});

test('scrub 移除完整及截断 PEM 正文，包括转义和 URL 编码', function (string $material) {
    $out = CredentialScrubber::scrub('invalid certificate: '.$material);
    expect($out)->toContain('invalid certificate:')->toContain('[redacted]')
        ->not->toContain('SYNTHETIC-PRIVATE-BODY')->not->toContain('BEGIN')->not->toContain('END');
})->with([
    "-----BEGIN PRIVATE KEY-----\nSYNTHETIC-PRIVATE-BODY\n-----END PRIVATE KEY-----",
    '-----BEGIN RSA PRIVATE KEY-----\\nSYNTHETIC-PRIVATE-BODY\\n-----END RSA PRIVATE KEY-----',
    "-----BEGIN CERTIFICATE-----\nSYNTHETIC-PRIVATE-BODY",
    rawurlencode("-----BEGIN PRIVATE KEY-----\nSYNTHETIC-PRIVATE-BODY\n-----END PRIVATE KEY-----"),
    urlencode("-----BEGIN EC PRIVATE KEY-----\nSYNTHETIC-PRIVATE-BODY\n-----END EC PRIVATE KEY-----"),
]);

test('scrub 分别移除相邻 PEM 材料并保留后续业务说明', function (string $separator) {
    $message = "bad -----BEGIN CERTIFICATE-----\nCERT-BODY\n-----END CERTIFICATE-----"
        .$separator."-----BEGIN PRIVATE KEY-----\nPRIVATE-BODY\n-----END PRIVATE KEY----- reason";
    expect(CredentialScrubber::scrub($message))->toBe('bad [redacted]'.$separator.'[redacted] reason');
})->with([' ', "\n", '']);

test('scrub 移除常见凭证字段及认证头的完整值', function (string $message) {
    expect(CredentialScrubber::scrub($message))->toContain('[redacted]')
        ->not->toContain('SYNTHETIC-SECRET')->not->toContain('SECOND-SECRET');
})->with([
    '{"api_token":"SYNTHETIC-SECRET SECOND-SECRET","reason":"denied"}',
    '{"api_token":"SYNTHETIC-SECRET\\" SECOND-SECRET","reason":"denied"}',
    "{'AccessKeySecret': 'SYNTHETIC-SECRET SECOND-SECRET'}",
    'api_key=SYNTHETIC-SECRET&token=SECOND-SECRET',
    'api_token=SYNTHETIC-SECRET%26SECOND-SECRET',
    '{"api_token":"SYNTHETIC-SECRET%22SECOND-SECRET"}',
    'client_secret: SYNTHETIC-SECRET',
    '{"secret_access_key":"SYNTHETIC-SECRET"}',
    'X-Amz-Credential=SYNTHETIC-SECRET&X-Amz-Signature=SECOND-SECRET',
    'X-Auth-Key: SYNTHETIC-SECRET',
    'Authorization: Bearer SYNTHETIC-SECRET',
    'Authorization: AWS4-HMAC-SHA256 Credential=SYNTHETIC-SECRET, Signature=SECOND-SECRET',
    'Bearer SYNTHETIC-SECRET',
    rawurlencode('{"private_key":"SYNTHETIC-SECRET","password":"SECOND-SECRET"}'),
]);

test('scrub 丢弃签名请求回显及其后续正文', function (string $label) {
    $out = CredentialScrubber::scrub("signature mismatch. $label is [POST /\nSYNTHETIC-SECRET]");
    expect($out)->toBe('signature mismatch. [redacted]');
})->with(['StringToSign', 'CanonicalRequest', 'String to sign', 'Canonical Request']);

test('scrub 保留正常业务文案和无凭证编码内容', function (string $message) {
    expect(CredentialScrubber::scrub($message))->toBe($message);
})->with([
    'private key has to be in PEM format',
    'API token is invalid; please check credentials',
    '[AccessDenied] permission denied request id: req-123',
    'invalid domain: foo%20bar.example',
]);

test('加固生效证明：腾讯 sanitizer 正常透传分支含凭证时被兜底 redact', function () {
    // 腾讯 sanitizer 设计为「透传 SDK 自身 message」（威胁模型假设凭证在 TC3 头、不入 message）。
    // 但若某天 SDK message 意外带出凭证（边界被破），兜底必须拦下。构造一个 message 含凭证的
    // TencentCloudSDKException —— 走的是正常透传分支（[code] message），断言凭证被 scrub。
    $leakMsg = 'auth failed: secret_id=AKIDz8krbsJ5yKBZQpnEXAMPLE secret_key=wJalrXUtnFEMIK7MDENGEXAMPLEKEY AccessKeyId=AKIAEXAMPLE123456789 Signature=wJalrXUtnFEMIbcdEXAMPLEKEY';
    $out = TencentErrorSanitizer::sanitize(new TencentCloudSDKException('AuthFailure', $leakMsg, 'req-1'));

    // 错误码保留（响应体可读信息），但凭证子串全被兜底抹掉
    expect($out)->toContain('AuthFailure');
    expect($out)
        ->not->toContain('AKIDz8krbsJ5yKBZQpnEXAMPLE')
        ->not->toContain('wJalrXUtnFEMIK7MDENGEXAMPLEKEY')
        ->not->toContain('AKIAEXAMPLE123456789')
        ->not->toContain('wJalrXUtnFEMIbcdEXAMPLEKEY')
        ->not->toContain('secret_id=')
        ->not->toContain('secret_key=')
        ->not->toContain('Signature=');
});

test('加固生效证明：阿里 sanitizer 结构化分支 Message 含凭证时被兜底 redact', function () {
    // 阿里结构化错误分支取 data['Message']（响应体，正常不含凭证）。若上游响应 Message 意外混入凭证，
    // 兜底拦下。构造 data['Message'] 含凭证的 TeaError，断言走 [code] Message 分支后凭证被 scrub。
    $leak = new TeaError([
        'code' => 'SomeError',
        'message' => 'whatever',
        'data' => ['Code' => 'SomeError', 'Message' => 'leaked AccessKeyId=AKIAEXAMPLE123456789 Signature=wJalrXUtnFEMIbcdEXAMPLEKEY', 'RequestId' => 'r'],
    ]);
    $out = AliyunErrorSanitizer::sanitize($leak);

    expect($out)->toContain('SomeError');
    expect($out)
        ->not->toContain('AKIAEXAMPLE123456789')
        ->not->toContain('wJalrXUtnFEMIbcdEXAMPLEKEY')
        ->not->toContain('AccessKeyId=')
        ->not->toContain('Signature=');
});
