import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";
import vm from "node:vm";

function loadApi(handler: (url: string) => unknown) {
  const calls: string[] = [];
  const context = vm.createContext({
    window: {},
    AbortSignal,
    fetch: async (url: string) => {
      calls.push(url);
      const data = handler(url);
      return { ok: true, json: async () => data };
    }
  });
  vm.runInContext(
    readFileSync(
      new URL(
        "../../../plugins/easy/frontend/web/js/config.js",
        import.meta.url
      ),
      "utf8"
    ),
    context
  );
  context.Config = context.window.Config;
  vm.runInContext(
    readFileSync(
      new URL("../../../plugins/easy/frontend/web/js/api.js", import.meta.url),
      "utf8"
    ),
    context
  );
  return { api: context.window.API, calls };
}

test("简易申请页外部明确不匹配不调用本站", async () => {
  const { api, calls } = loadApi(() => ({
    code: 0,
    errors: [{ domain: "example.com", matched: "false" }]
  }));
  const result = await api.verifyDCV({
    domain: "example.com",
    method: "cname",
    value: "target.test"
  });
  assert.equal(result.checked, false);
  assert.equal(calls.length, 1);
  assert.ok(calls[0].startsWith("https://"));
});

test("简易申请页 unknown 继续到本地 CNAME 回落", async () => {
  const { api, calls } = loadApi(url => ({
    data: {
      results: {
        "example.com": {
          matched: url === "/api/dcv/verify" ? "true" : "unknown"
        }
      }
    }
  }));
  const result = await api.verifyCname("example.com", "_certum", "target.test");
  assert.equal(result.checked, true);
  assert.equal(calls.length, 3);
  assert.equal(calls.at(-1), "/api/dcv/verify");
});

test("简易申请页 TXT 查询异常回落且排除 CNAME 链上的记录", async () => {
  const { api, calls } = loadApi(url => {
    if (url.startsWith("https:")) throw new Error("unavailable");
    return {
      code: 1,
      data: {
        records: [
          { name: "target.test", type: "TXT", value: "not-a-conflict" },
          { name: "_certum.example.com", type: "TXT", value: "conflict" }
        ]
      }
    };
  });
  assert.deepEqual(
    Array.from(await api.queryTxtRecords("_certum.example.com")),
    ["conflict"]
  );
  assert.equal(calls.at(-1), "/api/dns/query");
});
