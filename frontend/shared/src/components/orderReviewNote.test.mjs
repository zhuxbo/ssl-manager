import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";
import { createSSRApp } from "vue";
import { parse, compileTemplate } from "vue/compiler-sfc";
import { renderToString } from "vue/server-renderer";

async function compile(source, filename) {
  const result = compileTemplate({
    source,
    filename,
    id: "review-note-test",
    ssr: true,
    ssrCssVars: []
  });
  assert.deepEqual(result.errors, []);
  const code = result.code.replace(
    /from "(vue(?:\/server-renderer)?)"/g,
    (_, name) => `from ${JSON.stringify(import.meta.resolve(name))}`
  );
  return (
    await import(
      `data:text/javascript;base64,${Buffer.from(code).toString("base64")}`
    )
  ).ssrRender;
}

const noteFile = new URL("./OrderReviewNote.vue", import.meta.url);
const noteTemplate = parse(readFileSync(noteFile, "utf8")).descriptor.template;
const noteComponent = {
  props: ["note"],
  ssrRender: await compile(noteTemplate.content, noteFile.pathname)
};
const frontendRoot = new URL("../../../", import.meta.url);

function documentRow(node) {
  if (node.tag === "tr" && node.loc.source.includes("<DocumentUpload")) {
    return node.loc.source;
  }
  for (const child of node.children ?? []) {
    const found = documentRow(child);
    if (found) return found;
  }
}

for (const channel of ["admin", "user"]) {
  for (const productType of ["ssl", "codesign", "docsign", "smime"]) {
    test(`${channel} ${productType} 文档区域展示审核意见并遵守显示条件`, async () => {
      const file = new URL(
        `${channel}/src/views/order/detail/components/${productType}/process.vue`,
        frontendRoot
      );
      const { descriptor } = parse(readFileSync(file, "utf8"));
      const row = documentRow(descriptor.template.ast);
      assert.ok(row, "产品详情必须有文档区域");
      const component = {
        props: ["order", "cert", "hasDocuments"],
        components: {
          OrderReviewNote: noteComponent,
          Documents: () => null,
          DocumentUpload: () => null
        },
        ssrRender: await compile(row, file.pathname)
      };
      async function render(
        note,
        ca = "certum",
        validationType = "ov",
        status = "processing"
      ) {
        return renderToString(
          createSSRApp(component, {
            order: {
              review_note: note,
              product: {
                ca,
                validation_type: validationType,
                product_type: productType
              }
            },
            cert: { status },
            hasDocuments: false
          })
        );
      }
      for (const validationType of ["ov", "ev"]) {
        const html = await render(
          "请补充授权书。\n<script>测试</script>",
          "certum",
          validationType
        );
        assert.ok(html.includes("审核意见"));
        assert.ok(
          html.includes("请补充授权书。\n&lt;script&gt;测试&lt;/script&gt;")
        );
        assert.ok(!html.includes("<script>"));
      }
      assert.ok(!(await render(null)).includes("审核意见"));
      assert.ok(!(await render("意见", "other")).includes("审核意见"));
      assert.ok(!(await render("意见", "certum", "dv")).includes("审核意见"));
      assert.ok(
        !(await render("意见", "certum", "ov", "active")).includes("审核意见")
      );
    });
  }
}
