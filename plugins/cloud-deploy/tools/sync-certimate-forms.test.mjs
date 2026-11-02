import assert from "node:assert/strict";
import fs from "node:fs";
import os from "node:os";
import path from "node:path";
import test from "node:test";
import { extractForm } from "./sync-certimate-forms.mjs";

function fixture(t, source) {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), "certimate-form-test-"));
  t.after(() => fs.rmSync(dir, { recursive: true, force: true }));
  const file = path.join(dir, "Form.tsx");
  fs.writeFileSync(file, source);
  return file;
}

test("静态提取有限选项、默认值和多层条件，不执行上游代码", t => {
  const file = fixture(
    t,
    `
    throw new Error("must never execute");
    const WEBSITE = "website" as const;
    const CERTIFICATE = "certificate" as const;
    const getInitialValues = () => ({ deployTarget: WEBSITE });
    const FormComponent = () => {
      const target = Form.useWatch([parent, "deployTarget"], form);
      const pattern = Form.useWatch([parent, "matchPattern"], form);
      return <>
        <Form.Item name={[parent, "deployTarget"]}><Select options={[WEBSITE, CERTIFICATE].map(s => ({value:s, label:s}))} /></Form.Item>
        <Show when={target === WEBSITE}><Show when={pattern !== "certsan"}>
          <Form.Item name={[parent, "siteId"]}><Input /></Form.Item>
        </Show></Show>
      </>;
    };
  `
  );
  assert.deepEqual(extractForm(file), {
    deployTarget: { options: ["website", "certificate"], default: "website" },
    siteId: {
      visible_when: {
        all: [
          { key: "deployTarget", equals: "website" },
          { key: "matchPattern", not_equals: "certsan" }
        ]
      }
    }
  });
});

test("未知动态选项和条件会失败，不能生成漏字段的成功基准", t => {
  const dynamicOptions = fixture(
    t,
    `<Form.Item name={[parent,"mode"]}><Select options={fetchOptions()} /></Form.Item>`
  );
  assert.throws(() => extractForm(dynamicOptions), /Unsupported options/);
  const dynamicCondition = fixture(
    t,
    `<Show when={allowed()}><Form.Item name={[parent,"id"]}><Input /></Form.Item></Show>`
  );
  assert.throws(
    () => extractForm(dynamicCondition),
    /Unsupported visibility condition/
  );
  const transformed = fixture(
    t,
    `<Form.Item name={[parent,"mode"]}><Select options={["a"].map(s=>({value:s.toUpperCase()}))} /></Form.Item>`
  );
  assert.throws(() => extractForm(transformed), /Non-identity/);
});
