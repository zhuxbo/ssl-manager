import assert from "node:assert/strict";
import test from "node:test";
import {
  queryDnsWithFallback,
  verifyDcvWithFallback
} from "../src/utils/dcv.ts";

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

test("请求失败继续轮询，全部失败才逐条本地回落", async () => {
  const calls: string[] = [];
  const results = await verifyDcvWithFallback(
    ["https://one.test", "https://two.test"],
    [item],
    async (url, items) => {
      calls.push(url);
      if (url.startsWith("https:")) throw new Error("timeout");
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

test("响应缺少部分域名结果时直接报告缺失，不额外回落", async () => {
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
  assert.deepEqual(calls, [[item.domain, second.domain, third.domain]]);
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

for (const [name, response] of [
  ["业务失败", { code: 0, msg: "查询失败" }],
  ["缺少结果", { code: 1, data: {} }],
  ["无法判定", { data: { results: { [item.domain]: { matched: "unknown" } } } }]
] as const) {
  test(`DCV ${name}不切换节点或回落`, async () => {
    const calls: string[] = [];
    const results = await verifyDcvWithFallback(
      ["https://one.test", "https://two.test"],
      [item],
      async url => {
        calls.push(url);
        return response;
      }
    );
    assert.deepEqual(calls, ["https://one.test/api/dcv/verify"]);
    assert.equal(results[item.domain].matched, "unknown");
    assert.ok(results[item.domain].error);
  });
}

for (const [name, response] of [
  ["业务错误", { code: 0, msg: "查询失败" }],
  ["缺少记录结构", { code: 1, data: {} }],
  ["无记录", { code: 1, data: { records: [] } }],
  [
    "不匹配记录",
    { code: 1, data: { records: [{ type: "TXT", value: "other" }] } }
  ]
]) {
  test(`DNS ${name}不切换节点或回落`, async () => {
    const calls: string[] = [];
    const result = await queryDnsWithFallback(
      ["https://one.test", "https://two.test"],
      { domain: item.domain, type: "TXT" },
      async url => {
        calls.push(url);
        return response as any;
      }
    );
    assert.deepEqual(calls, ["https://one.test/api/dns/query"]);
    assert.deepEqual(result, response);
  });
}

test("DNS 只有外部请求均失败才回落本站", async () => {
  const calls: string[] = [];
  await queryDnsWithFallback(
    ["https://one.test", "https://two.test"],
    { domain: item.domain, type: "TXT" },
    async url => {
      calls.push(url);
      if (url.startsWith("https:")) throw new Error("network error");
      return { code: 1, data: { records: [] } };
    }
  );
  assert.deepEqual(calls, [
    "https://one.test/api/dns/query",
    "https://two.test/api/dns/query",
    "/api/dns/query"
  ]);
});
