<?php

use App\Models\Acme\AcmeCert;
use App\Models\Cert;

test('ACME 路径命中-返回 key_authorization', function () {
    Cert::factory()->create([
        'common_name' => 'example.com',
        'status' => 'processing',
        'validation' => [
            ['domain' => 'example.com', 'method' => 'file_proxy', 'name' => 'acme-token-1', 'content' => 'key-auth-1', 'path' => '/.well-known/acme-challenge/acme-token-1', 'verified' => 0],
        ],
    ]);

    $this->get('http://example.com/.well-known/acme-challenge/acme-token-1')
        ->assertOk()
        ->assertSee('key-auth-1', false);
});

test('传统 API 路径命中-返回验证文件内容', function () {
    Cert::factory()->create([
        'common_name' => 'example.com',
        'status' => 'processing',
        'validation' => [
            ['domain' => 'example.com', 'method' => 'file', 'name' => 'ABC123.txt', 'content' => 'validation-content', 'verified' => 0],
        ],
    ]);

    $this->get('http://example.com/.well-known/pki-validation/ABC123.txt')
        ->assertOk()
        ->assertSee('validation-content', false);
});

test('token 不存在-返回 404', function () {
    $this->get('http://example.com/.well-known/acme-challenge/nonexistent-token')
        ->assertNotFound();
});

test('Host 域名不匹配-返回 404', function () {
    Cert::factory()->create([
        'status' => 'processing',
        'validation' => [
            ['domain' => 'example.com', 'method' => 'file_proxy', 'name' => 'acme-token-2', 'content' => 'key-auth-2', 'verified' => 0],
        ],
    ]);

    $this->get('http://other.com/.well-known/acme-challenge/acme-token-2')
        ->assertNotFound();
});

test('已签发证书不应命中-返回 404', function () {
    Cert::factory()->create([
        'status' => 'active',
        'validation' => [
            ['domain' => 'example.com', 'method' => 'file_proxy', 'name' => 'acme-token-3', 'content' => 'key-auth-3', 'verified' => 0],
        ],
    ]);

    $this->get('http://example.com/.well-known/acme-challenge/acme-token-3')
        ->assertNotFound();
});

test('域名大小写不敏感', function () {
    Cert::factory()->create([
        'common_name' => 'Example.COM',
        'status' => 'processing',
        'validation' => [
            ['domain' => 'Example.COM', 'method' => 'file_proxy', 'name' => 'acme-token-4', 'content' => 'key-auth-4', 'verified' => 0],
        ],
    ]);

    $this->get('http://example.com/.well-known/acme-challenge/acme-token-4')
        ->assertOk()
        ->assertSee('key-auth-4', false);
});

test('AcmeCert ACME 路径命中-返回 key_authorization', function () {
    AcmeCert::factory()->create([
        'common_name' => 'example.com',
        'status' => 'processing',
        'validation' => [
            ['domain' => 'example.com', 'method' => 'file_proxy', 'name' => 'acme-cert-token-1', 'content' => 'acme-cert-key-auth-1', 'path' => '/.well-known/acme-challenge/acme-cert-token-1', 'verified' => 0],
        ],
    ]);

    $this->get('http://example.com/.well-known/acme-challenge/acme-cert-token-1')
        ->assertOk()
        ->assertSee('acme-cert-key-auth-1', false);
});

test('AcmeCert 已签发证书不应命中-返回 404', function () {
    AcmeCert::factory()->create([
        'status' => 'active',
        'validation' => [
            ['domain' => 'example.com', 'method' => 'file_proxy', 'name' => 'acme-cert-token-2', 'content' => 'acme-cert-key-auth-2', 'verified' => 0],
        ],
    ]);

    $this->get('http://example.com/.well-known/acme-challenge/acme-cert-token-2')
        ->assertNotFound();
});
