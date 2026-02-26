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
develop/        # 开发环境
skills/         # 开发规范（详细文档）
```

## 核心指令

- **不要自动提交** - 完成修改后等待用户确认"提交"再执行 git commit/push
- **提交前格式化** - 后端 `./vendor/bin/pint`，前端 `pnpm prettier --write`
- **base 目录只读** - 通过 git subtree 同步上游代码，不要修改
- **PHP 8.3+** - 双引号变量不加大括号（如 `"$var"` 而非 `"{$var}"`）
- **测试发现 bug 必须修复代码** - 测试的目的是发现 bug 并修复，绝不修改测试去迎合错误的代码

## 开发规范

详细规范见 `skills/SKILL.md`，按领域组织：

| Skill | 内容 |
|-------|------|
| `skills/backend-dev/` | Laravel API、ACME 协议、升级系统 |
| `skills/frontend-dev/` | Vue 3、Monorepo、共享组件 |
| `skills/deploy-ops/` | Docker、宝塔、环境配置 |
| `skills/build-release/` | 版本发布、打包、CI/CD |
| `skills/plugin-dev/` | 插件系统、IIFE 打包、安装/更新/卸载 |
| `skills/acme-e2e-test/` | Docker certbot 端到端测试（Manager + Gateway） |

## 知识积累

开发中确定的信息写入对应 skill 文件：
- 新的架构约定或设计模式
- 疑难问题的解决方案
- 文档中缺失的重要信息

## 功能特性

### 委托验证 (delegation)

- `delegation` 提交到 CA 时转换为 `txt`，通过 `dcv.is_delegate` 标记区分
- 产品同步时保留本地的 `delegation` 验证方法
- 详见 `skills/backend-dev/SKILL.md` 委托验证章节

### 插件系统

- **插件目录**：`plugins/` 下按名称组织，每个插件包含 `plugin.json`
- **动态加载**：`PluginServiceProvider` 自动扫描、注册命名空间和 ServiceProvider
- **安全机制**：autoload 使用 `realpath()` 防路径遍历；公共端点仅返回 bundle/css 路径
- **前端加载**：公共 `GET /api/plugins` 返回 bundle 路径，管理端返回完整信息；`plugin-loader.ts`（`@shared/utils/plugin-loader`）统一加载，校验 URL 必须以 `/` 开头
- **共享依赖**：`exposeSharedDeps()` 暴露 Vue/Router/ElementPlus/Pinia + `getAccessToken()`
- **解耦原则**：主系统不硬引用插件代码/表，通过动态扫描（`_logs` 后缀表、`user_id` 字段）兼容插件数据
- **插件打包**：`plugins/{name}/build.sh` + `release.json` 独立打包
- **插件管理**：`PluginManager` 提供安装/更新/卸载/检查更新，管理端 `/plugin` 页面操作
- **更新地址优先级**：`plugin.json.release_url`（第三方）→ `{主系统 release_url}/plugins/{name}`（官方）
- **插件 API**：`GET /api/admin/plugin/installed`、`GET /api/admin/plugin/check-updates`、`POST /api/admin/plugin/install`、`POST /api/admin/plugin/update`、`POST /api/admin/plugin/uninstall`

### ACME 多级代理

- **架构**：certbot → Manager A → Manager B → ... → CA，每级都有系统 orders/certs 记录
- **去掉 acme_orders 表**：订单/证书全部使用系统 `orders` + `certs` 表
- **产品标识**：`products.support_acme = 1` 标识 ACME 产品（不新增 product_type）
- **ID 映射**：每级独立 ID，通过映射字段关联上游（accountId→`orders.acme_account_id`、orderId→`certs.api_id`、challengeId→`acme_authorizations.acme_challenge_id`）。对外 REST API 统一使用 order.id（通过 `findCertByOrderId()` 查找 latestCert），与 Gateway 保持一致
- **AcmeAccount 精确关联**：`acme_accounts.order_id` 直接关联 Order，避免通过 user_id 查找错误（兼容旧数据回落到 user_id 查询）
- **AcmeApiService 核心原则**：所有方法「查本级 → 映射 ID → 调上游」，不能透传下游 ID 给上游
- **EAB 可复用**：同一 EAB 可多次注册 ACME 账户（Certum 订单级认证），`eab_used_at` 仅记录首次使用时间
- **createAccount 复用**：有有效 Order 时不重复扣费，统一返回现有 EAB
- **续费联动**：`BillingService::tryAutoRenew` 创建新 Order 后自动迁移 `AcmeAccount.order_id`，并通知上游创建新订单（best-effort）
- **DNS 委托自动化**：创建 ACME Order 时自动尝试通过委托写 TXT 记录（best-effort，不阻塞）
- **URL 标识**：使用 `cert.refer_id`（随机唯一字符串）替代 token
- **ACME 状态推导**：不存储状态字段，从 cert.status + acme_authorizations 推导
- **延迟扣费**：创建订阅时不扣费（cert.amount=0，purchased_count=0），推迟到 `new-order` 提交域名后按实际域名精确计费。`OrderUtil::getOrderTransaction()` + `Transaction::create()`（boot 事件自动更新余额）
- **ACME 取消**：cancel 端点（`DELETE /orders/{id}`）支持三种场景：pending（未扣费，快速清理）、processing（已扣费，通知上游+退费）、active（退费周期内通知上游+退费）。`Action::cancel()` 对 ACME cert 使用 `AcmeApiClient::cancelOrder` 替代 `api->cancel`
- **SAN 验证**：`ValidatorUtil::validateSansMaxCount()` + purchased count 追踪
- **不自动补齐根域名**：ACME 产品不调用 `DomainUtil::addGiftDomain()`
- **配置**：优先 `ca.acmeUrl`/`ca.acmeToken`，未设置时回落到 `ca.url`（路径替换为 `/api/acme`）/`ca.token`
- **AcmeApiClient**（原 UpstreamClient）：连接上级 ACME REST API
- **EAB 获取方式**：Deploy Token（`GET /api/deploy/acme/eab/{orderId}`）、用户端 API（`GET /api/user/acme/eab`）
- **Web 端 ACME 订阅**：`BillingService::createSubscription()` 供 Web 表单创建 ACME 订单，复用有效 Order 逻辑
- **ACME 订单创建路由**：Deploy `POST /api/deploy/acme/order`、User `POST /api/user/acme/order`、Admin `POST /api/admin/acme/order` + `GET /api/admin/acme/eab/{orderId}`
- **前端签发方式**：action.vue 增加"手工签发/ACME签发"选择器，ACME 模式精简表单（仅产品+有效期）
- **ACME 详情页**：通过 `order.latest_cert.channel === 'acme'` 判断，显示专用标签页（订单详情/EAB凭据/委托认证/颁发记录）
- **Server URL 统一**：使用 `get_system_setting('site', 'url')` 替代 `config('app.url')`
- **不支持 EAB 重置**：上游 gateway 不支持，已移除 `resetEab` 方法和路由
- **RFC 8555 路由兼容**：order/authz/cert 路由同时支持 GET 和 POST（certbot finalize 后用 GET 轮询 order）
- **ACME 异常处理**：`ApiExceptions` 对 ACME 路由返回 RFC 7807 格式（`urn:ietf:params:acme:error:*`），避免通用 `{"code":0}` 格式
- **ACME 日志排除**：`LogOperation` 排除 `acme/*` 路由，避免非标准请求格式干扰
- **E2E 测试**：Docker certbot → Manager → 上级系统 → CA，详见 `ACME.md`

### 自动续费/重签

- `orders.auto_renew`: 订单级自动续费开关（null 时回落到用户设置）
- `orders.auto_reissue`: 订单级自动重签开关（null 时回落到用户设置）
- `users.auto_settings`: 用户级默认设置 `{"auto_renew": false, "auto_reissue": false}`
- `AutoRenewCommand` 同时处理续费和重签，根据订单周期与证书到期时间差判断

## 测试

- 纯单元测试：`php artisan test --exclude-group=database`
- 全部测试需 MySQL 连接
- 详见 `skills/backend-dev/SKILL.md` 测试章节
