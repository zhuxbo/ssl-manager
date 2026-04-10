# Manager Monorepo

> **维护指引**：保持本文件精简，仅包含项目概览和快速参考。详细规范写入 `skills/` 目录。

## 项目结构

```
frontend/
├── shared/     # 共享代码库（@shared/*）
├── admin/      # 管理端应用
├── user/       # 用户端应用
└── base/       # 上游框架（只读）
backend/        # Laravel 11 后端
plugins/        # 插件目录（独立功能模块）
build/          # 构建系统
deploy/         # 部署脚本
skills/         # 开发规范（详细文档）
```

## 核心指令

- **不要自动提交** - 完成修改后等待用户确认"提交"再执行 git commit/push
- **提交前格式化** - 后端 `./vendor/bin/pint`，前端 `pnpm prettier --write`
- **base 目录只读** - 通过 git subtree 同步上游代码，不要修改
- **PHP 8.3+** - 双引号变量不加大括号（如 `"$var"` 而非 `"{$var}"`）；例外：变量后紧跟中文等非 ASCII 字符时必须加花括号（`"{$var}，中文"` 而非 `"$var，中文"`），因为 PHP 变量名匹配 `\x80-\xff` 字节
- **测试发现 bug 必须修复代码** - 测试的目的是发现 bug 并修复，绝不修改测试去迎合错误的代码

## 开发规范

详细规范见 `skills/SKILL.md`，按领域组织：

| Skill                     | 内容                                           |
| ------------------------- | ---------------------------------------------- |
| `skills/backend-dev.md`   | Laravel API、ACME 协议、升级系统               |
| `skills/frontend-dev.md`  | Vue 3、Monorepo、共享组件                      |
| `skills/deploy-ops.md`    | Docker、宝塔、环境配置                         |
| `skills/build-release.md` | 版本发布、打包、CI/CD                          |
| `skills/plugin-dev.md`    | 插件系统、IIFE 打包、安装/更新/卸载            |
| `skills/acme-e2e-test/`   | Docker certbot 端到端测试（Manager + 上游系统） |

## 知识积累

开发中确定的信息写入对应 skill 文件：

- 新的架构约定或设计模式
- 疑难问题的解决方案
- 文档中缺失的重要信息

## 功能特性

### 委托验证 (delegation)

- `delegation` 提交到 CA 时转换为 `txt`，通过 `dcv.is_delegate` 标记区分
- 产品同步时保留本地的 `delegation` 验证方法
- **委托前缀**：`_dnsauth`（DigiCert 系，精确匹配子域）、`_pki-validation`（Sectigo，模糊匹配回落根域）、`_certum`（Certum，同 Sectigo）。已移除 `_acme-challenge`（ACME 使用独立体系）
- 详见 `skills/backend-dev.md` 委托验证章节

### 插件系统

- **插件目录**：`plugins/` 下按名称组织，每个插件包含 `plugin.json`
- **动态加载**：`PluginServiceProvider` 自动扫描、注册命名空间和 ServiceProvider
- **安全机制**：autoload 使用 `realpath()` 防路径遍历；公共端点仅返回 bundle/css 路径
- **前端加载**：公共 `GET /api/plugins` 返回 bundle 路径，管理端返回完整信息；`plugin-loader.ts`（`@shared/utils/plugin-loader`）统一加载，校验 URL 必须以 `/` 开头
- **共享依赖**：`exposeSharedDeps()` 暴露 Vue/Router/ElementPlus/Pinia + `getAccessToken()`
- **Widget 插槽**：`__registerPlugin` 支持 `widgets` 字段，插件可向已有页面注入组件（如 Dashboard 横幅）；已定义插槽：`user-dashboard-top`（用户端 Dashboard 顶部）
- **版本兼容**：`PluginManager.checkCompatibility()` 通过 `requires` 字段（如 `>=1.0.0`）检查主系统版本
- **解耦原则**：主系统不硬引用插件代码/表，通过动态扫描（`_logs` 后缀表、`user_id` 字段）兼容插件数据
- **插件打包**：`plugins/{name}/build.sh` + `release.json` 独立打包
- **插件管理**：`PluginManager` 提供安装/更新/卸载/检查更新，管理端 `/plugin` 页面操作
- **更新地址优先级**：`plugin.json.release_url`（第三方）→ `{主系统 release_url}/plugins/{name}`（官方）
- **插件 API**：`GET /api/admin/plugin/installed`、`GET /api/admin/plugin/check-updates`、`POST /api/admin/plugin/install`、`POST /api/admin/plugin/update`、`POST /api/admin/plugin/uninstall`

### ACME 订阅管理

- **模型**：单一 `Acme` 模型（`App\Models\Acme`，表 `acmes`），`eab_hmac` 加密存储且默认 hidden
- **计费流程**：`Action` 三步流程：`new(array $params)`（unpaid/待支付）→ `pay(int $id)`（pending/待提交）→ `commit(int $id)`（提交 上游系统 → active）；`newAndCommit(array $params)` 一步完成三步（事务保护，失败回滚）
- **取消流程**：`commitCancel(int $id)`（标记 cancelling + 创建 Task `cancel_acme` + TaskJob 延时 123s）→ `cancel(int $id)`（由 TaskJob 调用，调 Api->cancel() + 退费），与传统订单共用 Task + TaskJob 机制
- **Transaction 类型**：`acme_order`/`acme_cancel`
- **产品标识**：`products.product_type = 'acme'`
- **Source API 层**：`Services/Acme/Api/` 按 `product.source` 路由，仅 `default` 源（和 Order 一致），`AcmeSourceApiInterface` 统一 `new`/`get`/`cancel`/`getProducts` 接口，`default/Sdk` 通过系统设置 `ca.acme_url`/`ca.acme_token`（回落到 `ca.url`/`ca.token`）调用 上游系统 `/api/acme/*` 端点
- **产品导入**：`Order\Action::importProduct()` 同时查询 Order 和 ACME 两端产品，合并后按 `api_id` 去重
- **控制器路由**：
    - API：`/api/acme/` — new, get, cancel, get-products（对下游代理，与 上游系统 对齐）
    - Admin：`/api/admin/acme/` — index, show, new, pay, commit, sync, commit-cancel, remark
    - User：`/api/user/acme/` — index, show, new, pay, commit, commit-cancel（限当前用户）
    - Deploy：`/api/deploy/acme/` — new（一步到位：创建+支付+提交）, get（含 EAB）
- **产品 API 分离**：`/api/v2/get-products` 排除 ACME 产品，`/api/acme/get-products` 仅返回 ACME 产品；下单页面产品选择器通过 `exclude_product_type=acme` 过滤
- **传统流程完全隔离**：ACME 通过独立控制器、服务和前端模块处理，与传统订单无交集；V2 API `new` 和 `Order\Action::initParams` 拒绝 ACME 产品

## 系统架构约定

- **`$order->latestCert` 非空保证**：由系统架构保证 latestCert 关系非空，查询时加 `with('latestCert')` 预加载即可，无需额外空值判断
- **`$this->error()` 方法**：来自 `ApiResponse` trait，调用后抛出异常终止执行，不会继续后续代码
- **取消/吊销不静默成功**：上游接口未返回明确成功时，一律返回失败；不允许跳过上游调用直接标记本地状态
- **ACME 计费流程**：`Action` 三步流程 `new→pay→commit` 详见"ACME 订阅管理"章节
- **ACME 取消策略**：未提交上游（无 api_id）的 pending 订单直接退费取消；已提交上游的订单通过延时任务调 Api->cancel() 后退费
- **Action 无 userId 构造参数**：`Acme\Action` 和 `Order\Action` 均无 `userId` 构造参数，通过 `app(Action::class)` 获取实例。用户隔离由 UserScope 全局作用域保证（`Authenticate`/`ApiAuthenticate` 中间件注册 Acme、ApiToken、Callback、CnameDelegation、Order、Fund、Transaction、Organization、Contact、OrderDocument），控制器在创建方法的 params 中传入 `user_id`。UserScope `apply()` 无条件执行 `where('user_id', ...)`，不做零值跳过
- **ACME Action 统一封装上游 API 调用**：所有上游 API 调用（new/get/cancel 等）必须通过 `Services/Acme/Action`，不允许控制器直接调 `Api`。操作方法接收 ID（int），创建方法接收参数数组。内部负责模型查询、参数过滤、返回值校正、重复提交防护、状态入库。控制器仅做请求验证 + 一行调用 Action

### Certum 验证文档上传

- **独立表**：`order_documents`（本地上传的文档）
- **不改 Cert.documents**：该字段仅存 Certum 同步回来的审核状态（只读），职责不同
- **多级代理传递**：用户/Admin 上传 → `order_documents` 表 → 提交到上游（base64 via V2 API）→ 逐级到 上游系统 → Certum SOAP
- **V2 端点**：`POST /api/v2/upload-document`（接收下游 base64）
- **显示条件**：`brand.toLowerCase() === 'certum'` 且 `validation_type !== 'dv'`
- **文件限制**：单文件 5MB，类型 PDF/JPG/JPEG/PNG/XADES，控制器层 `mimes` 验证
- **提交权限**：Admin 和 User 均可提交文档到上游

### 自动续费/重签

- `orders.auto_renew`: 订单级自动续费开关（null 时回落到用户设置）
- `orders.auto_reissue`: 订单级自动重签开关（null 时回落到用户设置）
- `users.auto_settings`: 用户级默认设置 `{"auto_renew": false, "auto_reissue": false}`
- `AutoRenewCommand` 每天 00:00 执行：证书到期前 14 天触发，订单剩余 ≤15 天续费、>15 天重签；API channel 订单由下游控制，不处理
- **延时提交**：Command 创建续费/重签 + 支付后不立即 commit，通过 Task 表创建延时 commit 任务（随机 0~8 小时），分散上游压力，8 点后人工可检查状态
- **产品条件**：续费要求 `product.status=1 && renew=1`；重签仅要求 `reissue=1`（产品禁用仍可重签）
- **参数继承**：从原订单提取 period/contact/organization/domains；CSR 按 `product.reuse_csr` 决定重用或生成
- **委托前置条件**：缺失委托记录时自动创建（`_dnsauth` 精确域名、回落前缀按根域）；DNS 验证采用宽松策略（所有 dnsTools + 本地全部尝试，任一匹配即有效），目的是尽可能发起续签

## 测试

- 纯单元测试：`php artisan test --parallel --exclude-group=database`
- 全部测试需 MySQL 连接，本地务必用 `--parallel` 与 CI 保持一致
- 详见 `skills/backend-dev.md` 测试章节

### M4 测试覆盖

- **Commands**（9 文件 55 用例）：AutoRenew、Expire、DelegationCheck、DelegationCleanup、Validate、Purge、ResetAdminPassword、ClearAllCache、UserData
- **Models**（14 文件）：Order、User、Cert、Admin、Product、Notification、NotificationTemplate、Contact、Organization、Fund、Transaction、CnameDelegation、ApiToken、Task
- **Middleware**（8 文件）：AdminAuthenticate、UserAuthenticate、ApiAuthenticate、DeployAuthenticate、LogOperation、RateLimiter、LoginRateLimiter、FlushLogs
- **ACME**：Unit/Services/Acme/ActionTest（27 用例）、Feature/Controllers/Admin/AcmeControllerTest（11 用例）、Feature/Controllers/User/AcmeControllerTest（9 用例）、Feature/Controllers/Deploy/AcmeControllerTest（5 用例）
- **Deploy**：Feature/Controllers/Deploy/OrderControllerTest（39 用例）：query（21）、callback（7）、update（8）、认证（2）、数据结构（1）
- 已有 Unit/Models 测试（DeployToken、OrderAutoFields、UserAutoSettings）不重复覆盖
