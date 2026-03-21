# 完成检查 — Manager

提交前逐项检查，跳过不涉及的部分。

---

## 1. 确定变更范围

运行 `git diff --stat` 和 `git diff --cached --stat`，确认本次改动涉及哪些目录（backend / frontend/admin / frontend/user / frontend/shared / plugins）。

---

## 2. 后端检查

> 目录：`backend/`

### 2.1 代码格式化

```bash
cd backend && ./vendor/bin/pint --test
```

有问题则 `./vendor/bin/pint` 修复。

### 2.2 测试

```bash
cd backend && php artisan test --parallel
```

全量测试必须通过。改动特定模块时优先跑对应测试：

- Models：`tests/Unit/Models/`
- ACME 单元：`tests/Unit/Services/Acme/`
- ACME 控制器：`tests/Feature/Controllers/Admin/AcmeControllerTest.php`、`tests/Feature/Controllers/User/AcmeControllerTest.php`
- Deploy 控制器：`tests/Feature/Controllers/Deploy/`

### 2.3 Laravel 专项检查

- [ ] 迁移幂等（`Schema::hasColumn`/`Schema::hasTable` 守卫），不写 down
- [ ] Model 的 `$fillable`、`$casts`、`$hidden` 是否需要更新
- [ ] Action 无 userId 构造参数（用户隔离由 UserScope 保证）
- [ ] 控制器只做请求验证 + 一行调用 Action
- [ ] ACME 三步流程（new → pay → commit）状态流转完整
- [ ] Sdk 通过 `ca.acme_url`/`ca.acme_token`（回落 `ca.url`/`ca.token`）调 Gateway
- [ ] Transaction 类型正确（`acme_order`/`acme_cancel` 等）
- [ ] 队列 Job 在测试环境同步执行（`QUEUE_CONNECTION=sync`）
- [ ] Observer 改动是否影响已有事件触发链

### 2.4 PHP 8.3 规范

- [ ] 双引号变量不加大括号（`"$var"` 而非 `"{$var}"`）
- [ ] 例外：变量后紧跟中文等非 ASCII 字符时必须加（`"{$var}，中文"`）

---

## 3. 前端检查

> 目录：`frontend/`

### 3.1 Lint 全量（admin + user + shared）

```bash
pnpm lint
```

在项目根目录运行，包含 ESLint + Prettier + Stylelint。

### 3.2 构建验证（admin + user 双端）

```bash
pnpm build
```

确认两端都构建成功。

### 3.3 Monorepo 专项检查

- [ ] 修改 `frontend/shared/` 后同时检查 admin 和 user 两端影响
- [ ] `frontend/base/` 保持只读（git subtree，不直接修改）
- [ ] `@shared/*` 路径别名引用正确解析
- [ ] Workspace 依赖（`workspace:*`）版本一致
- [ ] admin 和 user 各自的 API 路径前缀正确（不混用）

### 3.4 Vue 3 + Element Plus 专项

- [ ] 新增组件有 `defineOptions({ name: "XxxPage" })`（keep-alive 依赖组件名）
- [ ] `watch`/`watchEffect` 在组件卸载时清理
- [ ] `addEventListener`、`mitt.on`、定时器在 `onBeforeUnmount` 中移除
- [ ] `v-for` 有唯一 `:key`，不与 `v-if` 同时用在同一元素
- [ ] Pinia store 的 state 使用函数返回
- [ ] Element Plus 组件按需引入正确

### 3.5 样式检查

- [ ] 组件样式使用 `scoped`
- [ ] 深度选择器使用 `:deep()` 而非 `::v-deep` / `/deep/`
- [ ] TailwindCSS 类名与自定义 SCSS 无冲突

---

## 4. 插件检查（如涉及 plugins/ 目录）

- [ ] 插件遵循安全隔离原则
- [ ] Widget 插槽注册正确（如 `user-dashboard-top`）
- [ ] 插件动态加载不影响主应用启动

---

## 5. Git Diff 审查

```bash
git diff
git diff --cached
```

- [ ] 没有误改的文件（composer.lock、pnpm-lock.yaml 意外变更等）
- [ ] 没有 `dd()`、`dump()`、`console.log()`、`debugger` 调试残留
- [ ] 没有硬编码的 URL、密钥、Token
- [ ] 删除的代码直接删除，不注释保留
- [ ] `.env` / 配置文件没被意外修改
- [ ] 没有未使用的 `use`（PHP）或 `import`（TS/Vue）
- [ ] 新增命名符合项目风格

---

## 6. 文档同步

- [ ] 核心逻辑改动 → 更新 `CLAUDE.md`
- [ ] 核心逻辑改动 → 更新 `README.md`
- [ ] 模块架构改动 → 更新 `skills/*.md`

---

## 7. 已知局限性和潜在风险

按以下分类列出风险项：

### 兼容性风险

- Manager 调 Gateway 的接口是否版本一致（Sdk 调用参数/返回值）
- 数据库迁移能否在线上平滑执行
- API 变更是否影响 admin/user 两端前端
- 插件接口变更是否影响已安装插件

### 安全风险

- 新增 API 是否有认证中间件（JWT / Token）
- 用户输入是否经 Request 类验证
- UserScope 是否覆盖新增查询
- 支付相关改动（yansongda/pay）是否安全
- 敏感字段在响应中是否隐藏

### 数据风险

- 迁移是否导致数据丢失
- 批量操作有无数量限制
- 自动续费/重签（auto_renew/auto_reissue）逻辑是否受影响
- Excel 导出（phpspreadsheet）大数据量是否有内存问题

### 性能风险

- 是否引入 N+1 查询（检查 `with()` 预加载）
- 大表查询是否走索引
- 前端新依赖是否影响 bundle 体积（admin + user 分别检查）
- 通知服务（SMS/邮件）批量发送是否有限流

### 部署风险

- 是否需要跑迁移
- 是否需要 `php artisan config:clear` / `cache:clear`
- 是否需要重启队列 worker
- 前端构建产物是否需要清除 CDN 缓存
- 插件是否需要重新发布（`release-plugin.sh`）

---

逐项检查完毕后输出结果摘要和风险列表。
