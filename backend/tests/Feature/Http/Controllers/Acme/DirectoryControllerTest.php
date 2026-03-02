<?php

test('获取 ACME 目录端点', function () {
    $this->getJson('/acme/directory')
        ->assertOk()
        ->assertJsonStructure([
            'newNonce',
            'newAccount',
            'newOrder',
            'revokeCert',
            'keyChange',
            'meta' => ['termsOfService', 'website', 'externalAccountRequired'],
        ]);
});

test('ACME 目录包含正确的 URL 前缀', function () {
    $response = $this->getJson('/acme/directory')
        ->assertOk();

    $data = $response->json();
    expect($data['newNonce'])->toContain('/acme/new-nonce');
    expect($data['newAccount'])->toContain('/acme/new-acct');
    expect($data['newOrder'])->toContain('/acme/new-order');
    expect($data['revokeCert'])->toContain('/acme/revoke-cert');
});

test('ACME 目录 meta 标记需要 EAB', function () {
    $response = $this->getJson('/acme/directory')
        ->assertOk();

    expect($response->json('meta.externalAccountRequired'))->toBeTrue();
});

test('ACME 目录返回 JSON Content-Type', function () {
    $this->getJson('/acme/directory')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json');
});
