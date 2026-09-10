import assert from "node:assert/strict";
import test from "node:test";
import { verifyDcvWithFallback } from "../src/utils/dcv.ts";

const item = {
  domain: "example.com",
  method: "cname",
  host: "_certum",
  value: "label.proxy.test"
};

test("外部明确不匹配直接采用，不请求本站", async () => {
  const calls: string[] = [];
  const results = await verifyDcvWithFallback(
    ["https://dns.test"],
    [item],
    async url => {
      calls.push(url);
      return {
        errors: [{ domain: item.domain, matched: "false", value: "other.test" }]
      };
    }
  );
  assert.deepEqual(calls, ["https://dns.test/api/dcv/verify"]);
  assert.equal(results[item.domain].matched, "false");
});

test("服务无法判定与请求失败继续轮询，最后才逐条本地回落", async () => {
  const calls: string[] = [];
  const results = await verifyDcvWithFallback(
    ["https://one.test", "https://two.test"],
    [item],
    async (url, items) => {
      calls.push(url);
      if (url.includes("one.test"))
        return { data: { results: { [item.domain]: { matched: "unknown" } } } };
      if (url.includes("two.test")) throw new Error("timeout");
      assert.equal(items.length, 1);
      return { data: { results: { [item.domain]: { matched: "true" } } } };
    }
  );
  assert.deepEqual(calls, [
    "https://one.test/api/dcv/verify",
    "https://two.test/api/dcv/verify",
    "/api/dcv/verify"
  ]);
  assert.equal(results[item.domain].matched, "true");
});

test("部分结果保留，只将未检测项目回落且单条请求", async () => {
  const second = { ...item, domain: "second.test" };
  const third = { ...item, domain: "third.test" };
  const calls: string[][] = [];
  await verifyDcvWithFallback(
    ["https://dns.test"],
    [item, second, third],
    async (url, items) => {
      calls.push(items.map(item => item.domain));
      return { data: { results: { [items[0].domain]: { matched: "true" } } } };
    }
  );
  assert.deepEqual(calls, [
    [item.domain, second.domain, third.domain],
    [second.domain],
    [third.domain]
  ]);
});

test("没有配置时直接本地检测；失败必须覆盖旧成功状态", async () => {
  const results = await verifyDcvWithFallback(
    [],
    [{ ...item, checked: true }],
    async url => {
      assert.equal(url, "/api/dcv/verify");
      throw new Error("unavailable");
    }
  );
  assert.equal(results[item.domain].matched, "unknown");
  assert.equal(results[item.domain].error, "检测服务不可用");
});
