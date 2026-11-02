#!/usr/bin/env node
// 静态读取上游 TSX，不执行上游代码；不认识的选项/条件必须报错，不能静默遗漏。
import fs from "node:fs";
import path from "node:path";
import { createRequire } from "node:module";
import { execFileSync } from "node:child_process";
import { fileURLToPath } from "node:url";
const require = createRequire(
  new URL("../frontend/admin/package.json", import.meta.url)
);
const ts = require("typescript");

function walk(node, visit) {
  visit(node);
  ts.forEachChild(node, child => walk(child, visit));
}
function parse(file) {
  return ts.createSourceFile(
    file,
    fs.readFileSync(file, "utf8"),
    ts.ScriptTarget.Latest,
    true,
    ts.ScriptKind.TSX
  );
}
function value(node, constants) {
  if (!node) throw new Error("missing expression");
  if (
    ts.isAsExpression(node) ||
    ts.isParenthesizedExpression(node) ||
    ts.isSatisfiesExpression(node)
  )
    return value(node.expression, constants);
  if (ts.isStringLiteral(node) || ts.isNoSubstitutionTemplateLiteral(node))
    return node.text;
  if (ts.isNumericLiteral(node)) return Number(node.text);
  if (node.kind === ts.SyntaxKind.TrueKeyword) return true;
  if (node.kind === ts.SyntaxKind.FalseKeyword) return false;
  if (ts.isIdentifier(node) && constants.has(node.text))
    return constants.get(node.text);
  if (ts.isArrayLiteralExpression(node))
    return node.elements.map(item => value(item, constants));
  if (ts.isObjectLiteralExpression(node))
    return Object.fromEntries(
      node.properties.map(item => {
        if (!ts.isPropertyAssignment(item))
          throw new Error("unsupported object property");
        const key = ts.isComputedPropertyName(item.name)
          ? value(item.name.expression, constants)
          : item.name.text;
        return [key, value(item.initializer, constants)];
      })
    );
  if (
    ts.isCallExpression(node) &&
    node.expression.getText() === "Object.freeze"
  )
    return value(node.arguments[0], constants);
  if (ts.isPropertyAccessExpression(node)) {
    const object = value(node.expression, constants);
    if (Object.hasOwn(object, node.name.text)) return object[node.name.text];
  }
  if (ts.isElementAccessExpression(node)) {
    const object = value(node.expression, constants),
      key = value(node.argumentExpression, constants);
    if (Object.hasOwn(object, key)) return object[key];
  }
  if (ts.isTemplateExpression(node))
    return (
      node.head.text +
      node.templateSpans
        .map(
          span => String(value(span.expression, constants)) + span.literal.text
        )
        .join("")
    );
  throw new Error(`Unsupported expression: ${node.getText()}`);
}
function readConstants(source, constants = new Map()) {
  walk(source, node => {
    if (
      ts.isVariableDeclaration(node) &&
      ts.isIdentifier(node.name) &&
      node.initializer
    ) {
      try {
        constants.set(node.name.text, value(node.initializer, constants));
      } catch {
        /* 动态局部值在消费处严格解析。 */
      }
    }
  });
  return constants;
}
function attribute(opening, name) {
  const attr = opening.attributes.properties.find(
    item => ts.isJsxAttribute(item) && item.name.getText() === name
  );
  return attr?.initializer && ts.isJsxExpression(attr.initializer)
    ? attr.initializer.expression
    : attr?.initializer;
}

export function extractForm(file, importedConstants = new Map()) {
  const source = parse(file),
    constants = readConstants(source, new Map(importedConstants));
  const watches = new Map(),
    defaults = {},
    fields = {};
  walk(source, node => {
    if (!ts.isVariableDeclaration(node) || !node.initializer) return;
    if (
      ts.isIdentifier(node.name) &&
      ts.isCallExpression(node.initializer) &&
      node.initializer.expression.getText() === "Form.useWatch"
    ) {
      const arg = node.initializer.arguments[0];
      if (ts.isArrayLiteralExpression(arg)) {
        try {
          watches.set(node.name.text, value(arg.elements.at(-1), constants));
        } catch {
          /* 共享文件格式 hook 在下方显式适配。 */
        }
      }
    }
    // 上游 Local/S3 的共享 hook 默认监视 fileFormat；仅接受未覆盖 format 的调用。
    if (
      ts.isObjectBindingPattern(node.name) &&
      ts.isCallExpression(node.initializer) &&
      /useSharedFormFieldsAndHandlers$/.test(
        node.initializer.expression.getText()
      )
    ) {
      const options = node.initializer.arguments[1];
      if (
        options &&
        (!ts.isObjectLiteralExpression(options) ||
          options.properties.some(p => p.name?.getText() === "format"))
      )
        throw new Error("Shared format hook changed");
      for (const item of node.name.elements)
        if (item.propertyName?.getText() === "fieldFormat")
          watches.set(item.name.getText(), "fileFormat");
    }
    if (node.name.getText() === "getInitialValues") {
      const collectDefaults = expression => {
        while (ts.isParenthesizedExpression(expression))
          expression = expression.expression;
        if (!ts.isObjectLiteralExpression(expression))
          throw new Error("Unsupported initial values expression");
        for (const item of expression.properties)
          if (ts.isPropertyAssignment(item)) {
            try {
              defaults[item.name.getText()] = value(
                item.initializer,
                constants
              );
            } catch {
              /* 动态默认值不作为静态契约。 */
            }
          }
      };
      if (
        ts.isArrowFunction(node.initializer) &&
        !ts.isBlock(node.initializer.body)
      )
        collectDefaults(node.initializer.body);
      else
        walk(node.initializer, child => {
          if (ts.isReturnStatement(child) && child.expression)
            collectDefaults(child.expression);
        });
    }
  });
  function condition(node) {
    if (ts.isParenthesizedExpression(node)) return condition(node.expression);
    if (ts.isBinaryExpression(node)) {
      const operator = node.operatorToken.getText();
      if (operator === "&&" || operator === "||")
        return {
          [operator === "&&" ? "all" : "any"]: [
            condition(node.left),
            condition(node.right)
          ]
        };
      if (["===", "!=="].includes(operator) && watches.has(node.left.getText()))
        return {
          key: watches.get(node.left.getText()),
          [operator === "===" ? "equals" : "not_equals"]: value(
            node.right,
            constants
          )
        };
    }
    throw new Error(`Unsupported visibility condition: ${node.getText()}`);
  }
  walk(source, node => {
    if (
      !ts.isJsxElement(node) ||
      node.openingElement.tagName.getText() !== "Form.Item"
    )
      return;
    const name = attribute(node.openingElement, "name");
    if (!name) return;
    if (!ts.isArrayLiteralExpression(name))
      throw new Error(`Unsupported field name: ${name.getText()}`);
    const key = value(name.elements.at(-1), constants),
      field = {},
      conditions = [];
    for (let parent = node.parent; parent; parent = parent.parent) {
      if (
        ts.isJsxElement(parent) &&
        parent.openingElement.tagName.getText() === "Show"
      )
        conditions.unshift(condition(attribute(parent.openingElement, "when")));
    }
    if (conditions.length)
      field.visible_when =
        conditions.length === 1 ? conditions[0] : { all: conditions };
    walk(node, child => {
      if (!ts.isJsxSelfClosingElement(child) && !ts.isJsxOpeningElement(child))
        return;
      if (!["Select", "Radio.Group"].includes(child.tagName.getText())) return;
      const options = attribute(child, "options");
      if (
        ts.isCallExpression(options) &&
        ts.isPropertyAccessExpression(options.expression) &&
        options.expression.name.text === "map"
      ) {
        // 只接受原值映射为 value 的有限选项；转换函数变化不能沿用旧契约。
        const callback = options.arguments[0];
        let identity = false;
        walk(callback, item => {
          if (
            ts.isPropertyAssignment(item) &&
            item.name.getText() === "value" &&
            item.initializer.getText() ===
              callback.parameters?.[0]?.name.getText()
          )
            identity = true;
        });
        if (!identity)
          throw new Error(`Non-identity options mapping: ${options.getText()}`);
        field.options = value(options.expression.expression, constants);
      } else if (ts.isArrayLiteralExpression(options)) {
        field.options = options.elements.map(item =>
          value(
            item.properties.find(p => p.name.getText() === "value").initializer,
            constants
          )
        );
      } else throw new Error(`Unsupported options: ${options?.getText()}`);
      if (Object.hasOwn(defaults, key)) field.default = defaults[key];
    });
    if (Object.hasOwn(fields, key)) throw new Error(`Duplicate field: ${key}`);
    fields[key] = field;
  });
  return fields;
}

export function buildFixture(root) {
  const ui = path.join(root, "ui/src"),
    formsDir = path.join(ui, "components/workflow/designer/forms");
  const domain = readConstants(parse(path.join(ui, "domain/provider.ts")));
  const formats = readConstants(parse(path.join(ui, "domain/certificate.ts")));
  const deploymentProviders = domain.get("DEPLOYMENT_PROVIDERS");
  if (!deploymentProviders) throw new Error("Missing DEPLOYMENT_PROVIDERS");
  const formMap = {};
  const registry = parse(
    path.join(formsDir, "BizDeployNodeConfigFieldsProvider.tsx")
  );
  walk(registry, node => {
    if (
      !ts.isPropertyAssignment(node) ||
      !ts.isComputedPropertyName(node.name) ||
      !node.name.expression.getText().startsWith("DEPLOYMENT_PROVIDERS")
    )
      return;
    const slug = value(node.name.expression, domain);
    const component = node.initializer.getText();
    if (!/^BizDeployNodeConfigFieldsProvider\w+$/.test(component))
      throw new Error(`Unsupported form component: ${component}`);
    const filename = component + ".tsx";
    if (["ssh", "ftp", "local"].includes(slug)) return;
    formMap[slug] = {
      file: path.relative(root, path.join(formsDir, filename)),
      fields: extractForm(path.join(formsDir, filename), formats)
    };
  });
  const git = (...args) =>
    execFileSync("git", ["-C", root, ...args], { encoding: "utf8" }).trim();
  return {
    source: { repo: "certimate-go/certimate", sha: git("rev-parse", "HEAD") },
    scope:
      "部署表单有限选项、选择器默认值、Show 可见条件；不含动态校验和云 API 行为",
    forms: Object.fromEntries(
      Object.entries(formMap).sort(([a], [b]) => a.localeCompare(b, "en"))
    ),
    without_form: [...new Set(Object.values(deploymentProviders))]
      .filter(slug => !formMap[slug] && !["ssh", "ftp", "local"].includes(slug))
      .sort()
  };
}

if (
  process.argv[1] &&
  path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)
) {
  const [root, output, flag] = process.argv.slice(2);
  if (!root || (flag && flag !== "--check"))
    throw new Error(
      "Usage: node sync-certimate-forms.mjs /path/to/certimate [output.json] [--check]"
    );
  const json = JSON.stringify(buildFixture(root), null, 2) + "\n";
  if (flag === "--check") {
    if (!output || fs.readFileSync(output, "utf8") !== json)
      throw new Error(
        "Certimate form baseline differs; regenerate and run alignment tests"
      );
  } else if (output) fs.writeFileSync(output, json);
  else process.stdout.write(json);
}
