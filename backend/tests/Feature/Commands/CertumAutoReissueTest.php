<?php

use App\Exceptions\ApiResponseException;
use App\Models\Cert;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\SettingGroup;
use App\Models\Task;
use App\Models\User;
use App\Services\Notification\NotificationCenter;
use App\Services\Order\Action;
use App\Services\Order\AutoRenewService;
use App\Services\Order\Utils\CsrUtil;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 9, 20)->startOfDay());
    Queue::fake();
    $this->delegation = Mockery::mock(AutoRenewService::class);
    $this->app->instance(AutoRenewService::class, $this->delegation);
    $this->notifications = Mockery::mock(NotificationCenter::class);
    $this->notifications->shouldReceive('dispatch')->andReturnNull();
    $this->app->instance(NotificationCenter::class, $this->notifications);

    $group = SettingGroup::firstOrCreate(['name' => 'site'], ['title' => '站点设置']);
    $this->setting = Setting::create([
        'group_id' => $group->id, 'key' => 'firstAutoReissue', 'type' => 'integer', 'value' => 7,
    ]);
    $user = User::factory()->create(['auto_settings' => ['auto_renew' => false, 'auto_reissue' => true]]);
    $this->product = Product::factory()->create([
        'brand' => 'certum', 'ca' => 'certum', 'product_type' => 'ssl',
        'validation_methods' => ['txt', 'http', 'https', 'email', 'delegation'],
        'reuse_csr' => 1,
    ]);
    $this->order = Order::factory()->create([
        'user_id' => $user->id, 'product_id' => $this->product->id,
        'auto_reissue' => null, 'auto_renew' => false,
        'period_from' => now()->subDays(190), 'period_till' => now()->addDays(170),
    ]);
    $this->cert = Cert::factory()->active()->create([
        'order_id' => $this->order->id, 'channel' => 'web',
        'common_name' => 'example.com', 'alternative_names' => 'example.com',
        'expires_at' => now()->addDays(7), 'dcv' => ['method' => 'http'],
        'csr' => 'old-csr', 'private_key' => 'old-key',
    ]);
    $this->order->update(['latest_cert_id' => $this->cert->id]);
});

function expectCertumReissueParameters(string $method, bool $reuseCsr = false): void
{
    test()->delegation->shouldNotReceive('checkDelegationValidity');
    $action = Mockery::mock(Action::class);
    $action->shouldReceive('reissue')->once()->with(Mockery::on(fn ($params) => $params['validation_method'] === $method
        && ($reuseCsr
            ? ($params['csr'] ?? null) === test()->cert->csr && ($params['private_key'] ?? null) === test()->cert->private_key && ! isset($params['csr_generate'])
            : ($params['csr_generate'] ?? null) === 1 && ! isset($params['csr']) && ! isset($params['private_key']))
        && $params['channel'] === 'auto'
    ))->andThrow(new ApiResponseException('', null, ['order_id' => test()->order->id], 1));
    $action->shouldNotReceive('pay');
    $action->shouldReceive('createTask')->once()->with(test()->order->id, 'commit', Mockery::type('int'));
    app()->instance(Action::class, $action);
}

test('Certum 复用原验证方法且即使产品允许复用也重新生成 CSR', function (array $dcv, string $method) {
    $this->cert->update(['dcv' => $dcv]);
    expectCertumReissueParameters($method);
    $this->artisan('schedule:auto-renew')->assertSuccessful();
})->with([
    [['method' => 'txt'], 'txt'],
    [['method' => 'http'], 'http'],
    [['method' => 'https'], 'https'],
    [['method' => 'email'], 'email'],
    [['method' => 'txt', 'is_delegate' => true], 'delegation'],
]);

test('Certum 设置缺失或非法时仍走原委托检查', function (mixed $value, string $type) {
    if ($value === null) {
        $this->setting->delete();
    } else {
        $this->setting->update(['type' => $type, 'value' => $value]);
    }
    $this->delegation->shouldReceive('checkDelegationValidity')->once()->andReturn(false);
    $this->app->instance(Action::class, Mockery::mock(Action::class));
    $this->artisan('schedule:auto-renew')->assertSuccessful();
})->with([
    [null, 'integer'], [0, 'integer'], [4, 'integer'], [15, 'integer'],
    ['7.5', 'integer'], ['7abc', 'integer'], [7, 'string'], [7, 'float'],
]);

test('Certum 仅在配置天数窗口内免委托', function (int $days, int $seconds, bool $reuse) {
    $this->setting->update(['value' => $days]);
    $this->cert->update(['expires_at' => now()->addDays($days)->addSeconds($seconds)]);
    if ($reuse) {
        expectCertumReissueParameters('http');
    } else {
        $this->delegation->shouldReceive('checkDelegationValidity')->once()->andReturn(false);
    }
    $this->artisan('schedule:auto-renew')->assertSuccessful();
})->with([[5, 0, true], [7, 0, true], [14, 0, true], [7, 1, false], [7, -1, true]]);

test('Certum 按当前政策与 period_from 判断复用截止时间', function (string $date, int $ageDays, int $seconds, bool $reuse) {
    $this->travelTo(now()->parse($date)->setTimezone(config('app.timezone')));
    $this->order->update([
        'period_from' => now()->subDays($ageDays)->addSeconds($seconds),
        // SQL NOW() 不受 Carbon 测试时钟影响，订单余量保持在两种时钟的未来。
        'period_till' => '2035-01-01',
    ]);
    $this->cert->update(['expires_at' => now()->addDays(7)]);
    if ($reuse) {
        expectCertumReissueParameters('http');
    } else {
        $this->delegation->shouldReceive('checkDelegationValidity')->once()->andReturn(false);
    }
    $this->artisan('schedule:auto-renew')->assertSuccessful();
})->with([
    ['2026-03-14 00:00:00', 398, 1, true],
    ['2026-03-14 00:00:00', 398, 0, false],
    ['2026-03-15 00:00:00', 201, 0, false],
    ['2026-03-15 00:00:00', 200, 1, true],
    ['2026-03-15 00:00:00', 200, 0, false],
    ['2027-03-14 00:00:00', 199, 0, true],
    ['2027-03-15 00:00:00', 101, 0, false],
    ['2027-03-15 00:00:00', 100, 1, true],
    ['2027-03-15 00:00:00', 100, 0, false],
    ['2029-03-14 00:00:00', 99, 0, true],
    ['2029-03-15 00:00:00', 11, 0, false],
    ['2029-03-15 00:00:00', 10, 1, true],
    ['2029-03-15 00:00:00', 10, 0, false],
]);

test('Certum 新路径不扩大现有选单范围', function (string $excluded) {
    match ($excluded) {
        'api' => $this->cert->update(['channel' => 'api']),
        'expired' => $this->cert->update(['expires_at' => now()->subSecond()]),
        'processing' => $this->cert->update(['status' => 'processing']),
        'order-disabled' => $this->order->update(['auto_reissue' => false]),
        'user-disabled' => $this->order->user->update(['auto_settings' => ['auto_renew' => false, 'auto_reissue' => false]]),
        'no-reissue' => $this->product->update(['reissue' => 0]),
        'non-ssl' => $this->product->update(['product_type' => 'smime']),
        'period-ending' => $this->order->update(['period_till' => now()->addDays(15)]),
    };
    $this->delegation->shouldNotReceive('checkDelegationValidity');
    $this->app->instance(Action::class, Mockery::mock(Action::class));
    $this->artisan('schedule:auto-renew')->assertSuccessful();
})->with(['api', 'expired', 'processing', 'order-disabled', 'user-disabled', 'no-reissue', 'non-ssl', 'period-ending']);

test('Certum 缺少估算依据或非 Certum 时回落委托', function (string $reason) {
    match ($reason) {
        'missing-start' => $this->order->update(['period_from' => null]),
        'future-start' => $this->order->update(['period_from' => now()->addDay()]),
        'missing-method' => $this->cert->update(['dcv' => null]),
        'other-ca' => $this->product->update(['ca' => 'letsencrypt']),
    };
    $this->delegation->shouldReceive('checkDelegationValidity')->once()->andReturn(false);
    $this->app->instance(Action::class, Mockery::mock(Action::class));
    $this->artisan('schedule:auto-renew')->assertSuccessful();
})->with(['missing-start', 'future-start', 'missing-method', 'other-ca']);

test('按 CA 真实重签保留验证方式并按规则生成或复制密钥', function (string $ca, string $algorithm, int $bits) {
    $this->product->update(['ca' => $ca, 'brand' => 'other-brand']);
    $this->delegation->shouldNotReceive('checkDelegationValidity');
    $keys = CsrUtil::generate([
        'domains' => 'example.com',
        'encryption' => ['alg' => $algorithm, 'bits' => $bits, 'digest_alg' => 'sha256'],
    ]);
    $this->cert->update([
        ...$keys, 'encryption_alg' => strtoupper($algorithm), 'encryption_bits' => $bits,
    ]);
    $this->withoutMockingConsoleOutput();
    Artisan::call('schedule:auto-renew');
    $output = Artisan::output();
    $this->assertStringContainsString('待提交', $output);

    $newCert = $this->order->fresh()->latestCert;
    // 证书算法字段在签发后解析回填；待提交阶段直接检查生成 CSR 的真实公钥。
    $publicKey = openssl_pkey_get_details(openssl_csr_get_public_key($newCert->csr));
    expect($this->cert->fresh()->status)->toBe('reissued')
        ->and($newCert->status)->toBe('pending')
        ->and($newCert->last_cert_id)->toBe($this->cert->id)
        ->and($newCert->dcv['method'])->toBe('http')
        ->and($newCert->csr === $keys['csr'])->toBe($ca !== 'certum')
        ->and($newCert->private_key === $keys['private_key'])->toBe($ca !== 'certum')
        ->and($publicKey['type'])->toBe($algorithm === 'rsa' ? OPENSSL_KEYTYPE_RSA : OPENSSL_KEYTYPE_EC)
        ->and($publicKey['bits'])->toBe($bits)
        ->and(CsrUtil::matchKey($newCert->csr, $newCert->private_key))->toBeTrue();
    $task = Task::where('order_id', $this->order->id)->where('action', 'commit')->sole();
    expect($task->started_at->betweenIncluded(now(), now()->addHours(8)))->toBeTrue()
        ->and($this->order->fresh()->period_from->eq($this->order->period_from))->toBeTrue();
})->with([
    ['certum', 'rsa', 2048], ['certum', 'ecdsa', 256],
    ['sectigo', 'rsa', 2048], ['sectigo', 'ecdsa', 256],
    ['digicert', 'rsa', 2048], ['digicert', 'ecdsa', 256],
]);

test('新增 CA 遵守各自验证复用截止时间', function (string $ca, string $date, int $days, int $seconds, bool $reuse) {
    $this->travelTo(now()->parse($date)->setTimezone(config('app.timezone')));
    $this->product->update(['ca' => $ca, 'brand' => 'other-brand']);
    $this->order->update(['period_from' => now()->subDays($days)->addSeconds($seconds), 'period_till' => '2035-01-01']);
    $this->cert->update(['expires_at' => now()->addDays(7)]);
    if ($reuse) {
        expectCertumReissueParameters('http', true);
    } else {
        $this->delegation->shouldReceive('checkDelegationValidity')->once()->andReturn(false);
        $this->app->instance(Action::class, Mockery::mock(Action::class));
    }
    $this->artisan('schedule:auto-renew')->assertSuccessful();
})->with([
    ['sectigo', '2026-03-11 00:00:00', 398, 1, true],
    ['sectigo', '2026-03-12 00:00:00', 198, 1, true],
    ['sectigo', '2026-03-12 00:00:00', 198, 0, false],
    ['digicert', '2026-02-24 17:59:59 UTC', 397, 1, true],
    ['digicert', '2026-02-24 18:00:00 UTC', 199, 1, true],
    ['digicert', '2026-02-24 18:00:00 UTC', 199, 0, false],
    ['sectigo', '2027-03-15 00:00:00', 100, 0, false],
    ['digicert', '2027-03-15 00:00:00', 100, 0, false],
    ['sectigo', '2029-03-15 00:00:00', 10, 0, false],
    ['digicert', '2029-03-15 00:00:00', 10, 0, false],
]);

test('统一设置缺失或 API 通道时各 CA 不走免委托路径', function (string $ca, string $scenario) {
    $this->product->update(['ca' => $ca, 'brand' => 'certum']);
    if ($scenario === 'api') {
        $this->cert->update(['channel' => 'api']);
        $this->delegation->shouldNotReceive('checkDelegationValidity');
    } else {
        $this->setting->delete();
        $this->delegation->shouldReceive('checkDelegationValidity')->once()->andReturn(false);
    }
    $this->app->instance(Action::class, Mockery::mock(Action::class));
    $this->artisan('schedule:auto-renew')->assertSuccessful();
})->with(['certum', 'sectigo', 'digicert'])->with(['api', 'setting-missing']);
