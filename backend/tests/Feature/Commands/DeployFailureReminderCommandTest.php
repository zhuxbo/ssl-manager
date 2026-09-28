<?php

use App\Models\Admin;
use App\Models\AutoDeployReport;
use App\Models\Cert;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Notification\Builders\SystemAlertNotificationBuilder;
use App\Services\Notification\NotificationCenter;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

// TestCase + RefreshDatabase 由 Pest.php 对 Feature/Commands 自动应用

afterEach(function () {
    Carbon::setTestNow();
    Mockery::close();
});

beforeEach(function () {
    Carbon::setTestNow('2026-08-06 00:00:00');
    Admin::factory()->create(['email' => 'ops@example.com']);
    Cache::flush();
});

function makeDeploySummaryOrder(): array
{
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $order = Order::factory()->create(['user_id' => $user->id, 'product_id' => $product->id]);
    $cert = Cert::factory()->active()->create(['order_id' => $order->id]);
    $order->update(['latest_cert_id' => $cert->id]);

    return [$order, $cert];
}

function createDeploySummaryReport(Order $order, Cert $cert, string $at, ?string $ip): void
{
    Carbon::setTestNow($at);
    AutoDeployReport::create([
        'order_id' => $order->id,
        'cert_id' => $cert->id,
        'status' => 'failure',
        'ip' => $ip,
        'message' => '部署失败',
    ]);
    Carbon::setTestNow('2026-08-06 00:00:00');
}

function captureDeploySummaryNotifications(): object
{
    $state = new class
    {
        public array $intents = [];
    };
    $center = Mockery::mock(NotificationCenter::class);
    $center->shouldReceive('dispatch')->andReturnUsing(function ($intent) use ($state) {
        $state->intents[] = $intent;
    });
    app()->instance(NotificationCenter::class, $center);

    return $state;
}

test('前一完整自然日的跨订单失败合并为一封管理员告警', function () {
    [$order1, $cert1] = makeDeploySummaryOrder();
    [$order2, $cert2] = makeDeploySummaryOrder();
    createDeploySummaryReport($order1, $cert1, '2026-08-05 00:00:00', '203.0.113.1');
    createDeploySummaryReport($order1, $cert1, '2026-08-05 12:20:00', '203.0.113.1');
    createDeploySummaryReport($order2, $cert2, '2026-08-05 23:59:59', null);
    $state = captureDeploySummaryNotifications();

    $this->artisan('schedule:deploy-failure-reminder')->assertSuccessful();

    expect($state->intents)->toHaveCount(1);
    $context = $state->intents[0]->context;
    expect($context['category'])->toBe('deploy_failure')
        ->and($context['details']['report_count'])->toBe(3)
        ->and($context['details']['order_count'])->toBe(2)
        ->and($context['details']['client_failure_count'])->toBe(2)
        ->and($context['details']['server_failure_count'])->toBe(1)
        ->and($context['details']['订单_'.$order1->id])->toBe('失败 2 条')
        ->and($context['details']['订单_'.$order2->id])->toBe('失败 1 条');
});

test('聚合窗口排除更早历史和当天的失败', function () {
    [$oldOrder, $oldCert] = makeDeploySummaryOrder();
    [$windowOrder, $windowCert] = makeDeploySummaryOrder();
    [$currentOrder, $currentCert] = makeDeploySummaryOrder();
    createDeploySummaryReport($oldOrder, $oldCert, '2026-08-04 23:59:59', '203.0.113.1');
    createDeploySummaryReport($windowOrder, $windowCert, '2026-08-05 00:00:00', '203.0.113.2');
    createDeploySummaryReport($currentOrder, $currentCert, '2026-08-06 00:00:00', '203.0.113.3');
    $state = captureDeploySummaryNotifications();

    $this->artisan('schedule:deploy-failure-reminder')->assertSuccessful();

    expect($state->intents)->toHaveCount(1)
        ->and($state->intents[0]->context['details']['report_count'])->toBe(1)
        ->and($state->intents[0]->context['details']['订单_'.$windowOrder->id])->toBe('失败 1 条');
});

test('同一天重复执行不会重复发送聚合告警', function () {
    [$order, $cert] = makeDeploySummaryOrder();
    createDeploySummaryReport($order, $cert, '2026-08-05 00:00:00', '203.0.113.1');
    $state = captureDeploySummaryNotifications();

    $this->artisan('schedule:deploy-failure-reminder')->assertSuccessful();
    $this->artisan('schedule:deploy-failure-reminder')->assertSuccessful();

    expect($state->intents)->toHaveCount(1);
});

test('前一完整自然日没有失败时不发送告警', function () {
    [$order, $cert] = makeDeploySummaryOrder();
    createDeploySummaryReport($order, $cert, '2026-08-04 23:59:59', '203.0.113.1');
    $state = captureDeploySummaryNotifications();

    $this->artisan('schedule:deploy-failure-reminder')
        ->expectsOutputToContain('前一天无失败')
        ->assertSuccessful();

    expect($state->intents)->toBeEmpty();
});

test('次日窗口有新失败时再次发送且同日稍后重跑仍去重', function () {
    [$order, $cert] = makeDeploySummaryOrder();
    createDeploySummaryReport($order, $cert, '2026-08-05 12:00:00', null);
    createDeploySummaryReport($order, $cert, '2026-08-06 12:00:00', null);
    $state = captureDeploySummaryNotifications();
    $this->artisan('schedule:deploy-failure-reminder')->assertSuccessful();
    Carbon::setTestNow('2026-08-06 18:00:00');
    $this->artisan('schedule:deploy-failure-reminder')->assertSuccessful();
    expect($state->intents)->toHaveCount(1);

    Carbon::setTestNow('2026-08-07 00:00:00');
    $this->artisan('schedule:deploy-failure-reminder')->assertSuccessful();
    expect($state->intents)->toHaveCount(2)
        ->and($state->intents[1]->context['details']['window_start'])->toBe('2026-08-06 00:00:00')
        ->and($state->intents[1]->context['details']['window_end'])->toBe('2026-08-07 00:00:00')
        ->and($state->intents[1]->context['details']['report_count'])->toBe(1);
});

test('失败汇总调度每天零点执行', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command ?? '', 'schedule:deploy-failure-reminder'));
    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 0 * * *');
});

test('订单按数量拆信且经过真实 Builder 后完整保留，重跑只补派发失败批次', function () {
    $expected = [];
    for ($i = 0; $i < 21; $i++) {
        [$order, $cert] = makeDeploySummaryOrder();
        createDeploySummaryReport($order, $cert, '2026-08-05 12:00:00', null);
        $expected[] = '订单_'.$order->id;
    }
    $intents = [];
    $failedOnce = false;
    $center = Mockery::mock(NotificationCenter::class);
    $center->shouldReceive('dispatch')->andReturnUsing(function ($intent) use (&$intents, &$failedOnce) {
        if ($intent->context['details']['batch'] === 2 && ! $failedOnce) {
            $failedOnce = true;
            throw new RuntimeException('模拟派发失败');
        }
        $intents[] = $intent;
    });
    app()->instance(NotificationCenter::class, $center);
    $this->artisan('schedule:deploy-failure-reminder')->assertSuccessful();
    expect($intents)->toHaveCount(2);
    $this->artisan('schedule:deploy-failure-reminder')->assertSuccessful();
    expect($intents)->toHaveCount(3);
    $this->artisan('schedule:deploy-failure-reminder')->assertSuccessful();
    expect($intents)->toHaveCount(3);

    $actual = [];
    $counts = [];
    $builder = app(SystemAlertNotificationBuilder::class);
    foreach ($intents as $intent) {
        $details = $builder->build($intent, Admin::first())->data['details'];
        expect($details)->not->toHaveKey('_truncated');
        $orders = array_filter(array_keys($details), fn ($key) => str_starts_with($key, '订单_'));
        $counts[] = count($orders);
        array_push($actual, ...$orders);
        expect($details['batch_count'])->toBe(3)
            ->and($details['order_count'])->toBe(count($orders));
    }
    sort($actual);
    sort($expected);
    sort($counts);
    expect($actual)->toBe($expected)->and($counts)->toBe([1, 10, 10]);
});
