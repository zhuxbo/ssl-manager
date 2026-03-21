<?php

use App\Services\Order\Traits\ActionTrait;
use App\Traits\ApiResponse;

function periodTillHarness(): object
{
    return new class
    {
        use ActionTrait;
        use ApiResponse;

        public function calcPeriodTill(int $timestamp, int $months, int $plus = 0): int
        {
            return $this->calculatePeriodTill($timestamp, $months, $plus);
        }
    };
}

test('短周期按固定30天每月计算', function () {
    $harness = periodTillHarness();
    $timestamp = strtotime('2025-01-01 00:00:00');

    // 1个月 = 30天
    expect($harness->calcPeriodTill($timestamp, 1, 0))->toBe($timestamp + 30 * 86400 - 1);

    // 6个月 = 180天
    expect($harness->calcPeriodTill($timestamp, 6, 0))->toBe($timestamp + 180 * 86400 - 1);

    // 3个月 plus=1 不额外赠送
    expect($harness->calcPeriodTill($timestamp, 3, 1))->toBe($timestamp + 90 * 86400 - 1);
});

test('长周期按固定365天每年计算', function () {
    $harness = periodTillHarness();
    $timestamp = strtotime('2025-01-01 00:00:00');

    // 12个月 = 365天
    expect($harness->calcPeriodTill($timestamp, 12, 0))->toBe($timestamp + 365 * 86400 - 1);

    // 24个月 = 730天
    expect($harness->calcPeriodTill($timestamp, 24, 0))->toBe($timestamp + 730 * 86400 - 1);

    // 仅 12个月 plus=1 赠送30天 → 395天
    expect($harness->calcPeriodTill($timestamp, 12, 1))->toBe($timestamp + 395 * 86400 - 1);

    // 36个月 plus=1 不额外赠送 → 1095天
    expect($harness->calcPeriodTill($timestamp, 36, 1))->toBe($timestamp + 1095 * 86400 - 1);
});
