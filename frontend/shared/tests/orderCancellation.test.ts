import assert from "node:assert/strict";
import test from "node:test";
import {
  canRevokeOrderCancellation,
  canBatchRevokeOrderCancellation
} from "../src/utils/orderCancellation.ts";

const now = Date.parse("2026-09-27T12:00:00+08:00");
const row = (seconds: number, status = "cancelling") => ({
  latest_cert: {
    status,
    updated_at: new Date(now - seconds * 1000).toISOString()
  }
});

test("证书最后更新满一分钟才显示撤回", () => {
  assert.equal(canRevokeOrderCancellation(row(59), now), false);
  assert.equal(canRevokeOrderCancellation(row(60), now), true);
  assert.equal(canRevokeOrderCancellation(row(61), now), true);
  assert.equal(canRevokeOrderCancellation(row(120, "cancelled"), now), false);
  assert.equal(canRevokeOrderCancellation({}, now), false);
});

test("更新时间缺失或非法时不显示", () => {
  for (const updated_at of [
    undefined,
    "invalid",
    new Date(now).toISOString()
  ]) {
    assert.equal(
      canRevokeOrderCancellation(
        { latest_cert: { status: "cancelling", updated_at } },
        now
      ),
      false
    );
  }
  assert.equal(
    canRevokeOrderCancellation(
      {
        latest_cert: {
          status: "cancelling",
          updated_at: new Date(now - 60_000).toISOString()
        }
      },
      now
    ),
    true
  );
});

test("批量必须有取消中订单且全部满一分钟", () => {
  assert.equal(canBatchRevokeOrderCancellation([], now), false);
  assert.equal(
    canBatchRevokeOrderCancellation([row(120, "active")], now),
    false
  );
  assert.equal(
    canBatchRevokeOrderCancellation([row(120), row(59)], now),
    false
  );
  assert.equal(
    canBatchRevokeOrderCancellation([row(120), row(60), row(0, "active")], now),
    true
  );
});

test("证书再次更新会延后单笔和批量撤回", () => {
  const order = row(120);
  assert.equal(canRevokeOrderCancellation(order, now), true);
  order.latest_cert.updated_at = new Date(now).toISOString();
  assert.equal(canRevokeOrderCancellation(order, now), false);
  assert.equal(canBatchRevokeOrderCancellation([row(120), order], now), false);
  assert.equal(canRevokeOrderCancellation(order, now + 60_000), true);
});
