import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { createRequire } from "node:module";
import test from "node:test";
import { runInNewContext } from "node:vm";

const require = createRequire(
  new URL("../../admin/package.json", import.meta.url)
);
const ts = require("typescript");
const source = readFileSync(
  new URL("../src/utils/orderConfirmation.ts", import.meta.url),
  "utf8"
);

function setup() {
  let box: any;
  const exports: any = {};
  const ElMessageBox = {
    prompt(_description, _title, options) {
      return new Promise((resolve, reject) => {
        const state = {
          inputValue: "",
          action: "",
          confirmButtonLoading: false
        };
        let closed = false;
        const done = () => {
          closed = true;
          state.action === "confirm"
            ? resolve({ action: "confirm" })
            : reject(state.action);
        };
        box = {
          state,
          get closed() {
            return closed;
          },
          async trigger(action: string) {
            if (
              action === "confirm" &&
              options.inputValidator(state.inputValue) !== true
            )
              return;
            // Element Plus 在 beforeClose 前修改 action，done 按最新 action 结算。
            state.action = action;
            return options.beforeClose(action, state, done);
          }
        };
      });
    }
  };
  runInNewContext(
    ts.transpileModule(source, {
      compilerOptions: {
        module: ts.ModuleKind.CommonJS,
        target: ts.ScriptTarget.ES2022
      }
    }).outputText,
    { exports, require: () => ({ ElMessageBox }) }
  );
  return {
    confirm: exports.confirmOrderAction,
    cancel: exports.confirmOrderCancellation,
    get box() {
      return box;
    }
  };
}

test("确认要求精确输入，失败保留弹窗和输入并可重试", async () => {
  const context = setup();
  let calls = 0;
  const result = context.confirm("archive", "归档", async () => {
    if (++calls === 1) throw new Error("请求失败");
  });
  const { box } = context;
  for (const input of ["", "确认取消", " 确认归档", "确认归档 "]) {
    box.state.inputValue = input;
    await box.trigger("confirm");
  }
  assert.equal(calls, 0);
  box.state.inputValue = "确认归档";
  await box.trigger("confirm");
  assert.equal(box.closed, false);
  assert.equal(box.state.inputValue, "确认归档");
  assert.equal(box.state.confirmButtonLoading, false);
  await box.trigger("confirm");
  assert.equal(await result, true);
  assert.equal(calls, 2);
});

for (const closeAction of ["cancel", "close"]) {
  test(`提交中 ${closeAction} 不覆盖成功结果且不重复提交`, async () => {
    const context = setup();
    let finish!: () => void;
    let calls = 0;
    const pending = new Promise<void>(resolve => {
      finish = resolve;
    });
    const result = context.confirm("cancel", "取消", () => {
      calls++;
      return pending;
    });
    const { box } = context;
    box.state.inputValue = "确认取消";
    const request = box.trigger("confirm");
    assert.equal(box.state.confirmButtonLoading, true);
    await box.trigger("confirm");
    await box.trigger(closeAction);
    assert.equal(box.closed, false);
    assert.equal(calls, 1);
    finish();
    await request;
    assert.equal(await result, true);
    assert.equal(box.closed, true);
  });
}

for (const statuses of [["unpaid"], ["pending"], ["unpaid", "pending"]]) {
  test(`取消 ${statuses.join(",")} 直接提交且不弹确认`, async () => {
    const context = setup();
    let calls = 0;
    assert.equal(
      await context.cancel(statuses, "取消", async () => {
        calls++;
      }),
      true
    );
    assert.equal(calls, 1);
    assert.equal(context.box, undefined);
  });
}

test("直接取消失败不返回成功", async () => {
  const context = setup();
  assert.equal(
    await context.cancel(["pending"], "取消", async () => {
      throw new Error("请求失败");
    }),
    false
  );
  assert.equal(context.box, undefined);
});

for (const statuses of [
  ["processing"],
  ["active"],
  ["approving"],
  ["pending", "processing"]
]) {
  test(`取消 ${statuses.join(",")} 仍需确认`, async () => {
    const context = setup();
    let calls = 0;
    const result = context.cancel(statuses, "取消", async () => {
      calls++;
    });
    assert.equal(calls, 0);
    context.box.state.inputValue = "确认取消";
    await context.box.trigger("confirm");
    assert.equal(await result, true);
    assert.equal(calls, 1);
  });
}
