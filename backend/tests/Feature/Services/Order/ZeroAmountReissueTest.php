<?php

use App\Exceptions\ApiResponseException;
use App\Models\ApiToken;
use App\Models\Cert;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Order\Action;
use App\Services\Order\Api\Api;
use Tests\Traits\ActsAsAdmin;
use Tests\Traits\ActsAsUser;

uses(ActsAsAdmin::class, ActsAsUser::class);

test('手工重签按金额直接待提交或保留待支付', function (string $role, string $scenario, string $extraDomain, string $extraPrice, string $sourceStatus = 'active') {
    $user = User::factory()->withBalance('100.00')->create();
    $product = Product::factory()->create([
        'validation_methods' => ['txt'], 'gift_root_domain' => 0, 'total_min' => 1,
        'standard_min' => 0, 'wildcard_min' => 0,
    ]);
    $order = Order::factory()->create([
        'user_id' => $user->id, 'product_id' => $product->id, 'purchased_standard_count' => 1,
    ]);
    $cert = Cert::factory()->active()->create([
        'order_id' => $order->id, 'common_name' => 'a.example.com',
        'alternative_names' => 'a.example.com', 'standard_count' => 1,
        'status' => $sourceStatus,
    ]);
    $order->update(['latest_cert_id' => $cert->id]);
    if ($scenario !== 'same-domains') {
        ProductPrice::create([
            'product_id' => $product->id, 'level_code' => 'standard', 'period' => 12,
            'price' => '100.00', 'alternative_standard_price' => $extraPrice,
            'alternative_wildcard_price' => $extraPrice,
        ]);
    }
    $transactions = Transaction::count();
    $role === 'admin' ? $this->actingAsAdmin() : $this->actingAsUser($user);
    $prefix = $role === 'admin' ? '/api/admin' : '/api';
    $this->postJson($prefix.'/order/reissue', [
        'order_id' => $order->id,
        'domains' => $scenario === 'same-domains' ? 'a.example.com' : 'a.example.com,'.$extraDomain,
        'validation_method' => 'txt', 'csr_generate' => 1,
    ])->assertOk()->assertJsonPath('code', 1);

    $paid = $scenario === 'paid-extra';
    $wildcard = str_starts_with($extraDomain, '*.');
    $newCert = $order->fresh()->latestCert;
    expect($newCert->status)->toBe($paid ? 'unpaid' : 'pending')
        ->and($newCert->amount)->toBe($paid ? $extraPrice : '0.00')
        ->and($cert->fresh()->status)->toBe('reissued')
        ->and($user->fresh()->balance)->toBe('100.00')
        ->and(Transaction::count())->toBe($transactions)
        ->and($order->fresh()->purchased_standard_count)->toBe($scenario === 'free-extra' && ! $wildcard ? 2 : 1)
        ->and($order->fresh()->purchased_wildcard_count)->toBe($scenario === 'free-extra' && $wildcard ? 1 : 0);

    if ($paid) {
        try {
            app(Action::class)->pay($order->id, false);
        } catch (ApiResponseException $e) {
            expect($e->getApiResponse()['code'])->toBe(1);
        }
        expect($order->fresh()->latestCert->status)->toBe('pending')
            ->and($user->fresh()->balance)->toBe(bcsub('100.00', $extraPrice, 2))
            ->and(Transaction::count())->toBe($transactions + 1)
            ->and($order->fresh()->purchased_standard_count)->toBe($wildcard ? 1 : 2)
            ->and($order->fresh()->purchased_wildcard_count)->toBe($wildcard ? 1 : 0);
    } else {
        // 无零元付款流水时仍可取消待提交重签并恢复原证书。
        app(Action::class)->cancelPending($order->id);
        expect($order->fresh()->latest_cert_id)->toBe($cert->id)
            ->and($cert->fresh()->status)->toBe('active')
            ->and($user->fresh()->balance)->toBe('100.00')
            ->and(Transaction::count())->toBe($transactions);
    }
})->with(['admin', 'user'])->with([
    ['same-domains', 'b.example.com', '0.00'],
    ['free-extra', 'b.example.com', '0.00'],
    ['paid-extra', 'b.example.com', '10.00'],
    ['paid-extra', 'b.example.com', '0.01'],
    ['free-extra', '*.example.net', '0.00'],
    ['paid-extra', '*.example.net', '0.01'],
    'expired predecessor' => ['same-domains', 'b.example.com', '0.00', 'expired'],
]);

test('API 零元重签跳过支付后仍提交上游', function (string $version, string $idKey) {
    $user = User::factory()->create();
    $product = Product::factory()->create(['validation_methods' => ['txt'], 'gift_root_domain' => 0]);
    $order = Order::factory()->create(['user_id' => $user->id, 'product_id' => $product->id]);
    $cert = Cert::factory()->active()->create([
        'order_id' => $order->id, 'common_name' => 'a.example.com', 'alternative_names' => 'a.example.com',
        'dcv' => ['method' => 'txt'],
    ]);
    $order->update(['latest_cert_id' => $cert->id]);
    $transactions = Transaction::count();
    $api = Mockery::mock(Api::class);
    $api->shouldReceive('reissue')->once()->andReturn([
        'code' => 1, 'data' => ['api_id' => 'reissued-api-id', 'cert_apply_status' => 0],
    ]);
    app()->instance(Api::class, $api);
    $token = ApiToken::createToken($user->id);
    $this->withHeader('Authorization', "Bearer $token")->postJson('/api/'.$version.'/reissue', [
        $idKey => $order->id, 'csr_generate' => 1, 'domains' => 'a.example.com', 'validation_method' => 'txt',
    ])->assertOk()->assertJsonPath('code', 1);
    expect($order->fresh()->latestCert->status)->toBe('processing')
        ->and($order->fresh()->latestCert->amount)->toBe('0.00')
        ->and(Transaction::count())->toBe($transactions)
        ->and($user->fresh()->balance)->toBe('0.00');
})->with([['V1', 'oid'], ['v2', 'order_id']]);
