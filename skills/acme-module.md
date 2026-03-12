---
description: ACME 模块 - RFC 8555 协议服务端、上游对接、订阅计费、安全机制。修改 ACME 相关代码时自动加载。
---

# ACME 模块

Manager 作为 ACME RFC 8555 协议服务端，供 certbot 等客户端使用，并通过 REST API 连接上游（Gateway/上级 Manager）。

## 架构

```
certbot → Manager (ACME 服务) → Gateway/上级 Manager (REST API) → Certum
```

### 多级代理架构

```
certbot → Manager A (ACME 服务端) → Manager B / 上级系统 (REST API) → ... → CA
```

每一级都使用独立的 `acme_orders` + `acme_certs` 表（`App\Models\Acme` 命名空间）。连接的 ACME 服务返回的订单 ID 存入 `acme_certs.api_id`。

## ACME 端点 (`/acme/*`)

| 方法 | 端点 | 功能 |
|------|------|------|
| GET | `/acme/directory` | 目录 |
| HEAD/GET | `/acme/new-nonce` | 获取 Nonce |
| POST | `/acme/new-acct` | 注册账户（需 EAB） |
| POST | `/acme/new-order` | 创建订单 |
| POST | `/acme/authz/{token}` | 获取授权 |
| POST | `/acme/chall/{token}` | 响应验证 |
| POST | `/acme/order/{referId}/finalize` | 完成订单（cert.refer_id） |
| POST | `/acme/cert/{referId}` | 下载证书（cert.refer_id） |
| POST | `/acme/revoke-cert` | 吊销证书 |

## REST API 端点 (`/api/acme/*`)

供下级 Manager 调用，无 account 概念，路由风格 id 放后面：

- `POST /api/acme/orders` - 创建订单（参数：customer, product_code, domains, refer_id）
- `POST /api/acme/orders/reissue/{id}` - 重签订单（参数：domains, refer_id）
- `GET /api/acme/orders/{id}` - 获取订单
- `DELETE /api/acme/orders/{id}` - 取消订单
- `GET /api/acme/orders/authorizations/{id}` - 获取授权列表
- `POST /api/acme/orders/finalize/{id}` - 完成订单
- `GET /api/acme/orders/certificate/{id}` - 下载证书
- `POST /api/acme/challenges/respond/{id}` - 响应验证
- `POST /api/acme/certificates/revoke` - 吊销证书（by serial_number）

## 关键服务

| 服务 | 职责 |
|------|------|
| `JwsService` | JWS 解析和验证 |
| `NonceService` | Nonce 管理（Redis Cache::pull 原子操作） |
| `AccountService` | 账户管理 |
| `OrderService` | 订单管理（操作 Cert 代替 AcmeOrder） |
| `ApiService` | 订单创建 + 重签 + 取消（REST API 端点逻辑） |
| `BillingService` | 订阅创建（延迟扣费）、自动续费 |
| `ApiClient` | 连接的 ACME REST API 调用 |

## 数据模型

- ACME 使用独立的 `acme_orders` + `acme_certs` 表（`App\Models\Acme\AcmeOrder` / `AcmeCert`），与传统 `orders`/`certs` 完全隔离
- `products.product_type = 'acme'`（`Product::TYPE_ACME`）标识 ACME 产品
- `acme_certs.api_id` 存储连接的 ACME 服务的订单 ID
- `acme_certs.refer_id` 随机唯一字符串用于 ACME URL
- `acme_authorizations.cert_id` FK → acme_certs.id
- `acme_authorizations.acme_challenge_id` 连接的服务 challenge ID
- ACME 状态从 cert.status + acme_authorizations 推导，不存储；有 CSR 无证书 → processing
- `cert.status = 'processing'` 由 createOrder 上游成功后设置（区别于 ACME 协议状态推导，用于 commitCancel 区分取消场景）
- `OrderService::tryCompletePendingFinalize()` — processing 状态时向上游查询证书是否已签发

## 证书状态流转

```
pending ──[提交到上游成功]──→ processing ──[上游签发]──→ active
   │                            │                       │
   │                            ├──[取消]──→ cancelling ──[上游成功]──→ cancelled + 退费
   │                            │              │
   │                            │              └──[上游失败]──→ 保持 cancelling（系统重试）
   │                            │
   │                            └──[上游拒绝/异常]──→ failed
   │
   └──[取消]──→ 直接删除清理 + 退费（未提交上游，无 api_id）

                              active ──[取消]──→ cancelling ──[上游成功]──→ cancelled + 退费
                                     │              └──[上游失败]──→ 保持 cancelling
                                     │
                                     └──[吊销]──→ revoking ──[上游成功]──→ revoked
                                                    └──[上游失败]──→ 保持 revoking（系统重试）
```

**关键规则**：
- `cert.status = 'processing'` 由 createOrder 上游成功后设置
- 取消前先标记 `cancelling`；上游失败不回退，保持 `cancelling` 便于系统发现并重试
- 吊销前先标记 `revoking`；上游失败不回退，保持 `revoking` 便于系统发现并重试
- 未提交上游（无 api_id）的 pending 订单：直接删除 acme_authorizations + AcmeAccount + 退费
- 已提交上游的订单：必须上游明确成功后，才标记 `cancelled` / `revoked` + 退费，不删除订单和相关信息

## 订单取消

- **cancel 端点**：`DELETE /api/acme/orders/{id}`（ApiService::cancelOrder → BillingService::executeCancel）
- **pending 取消**（未提交域名、未扣费、无 api_id）：直接清理 acme_authorizations + AcmeAccount + 标记 cancelled
- **processing/active 取消**（已提交、已扣费、有 api_id）：先标记 cancelling → 通知上游 → 上游成功后标记 cancelled + 退费
- **上游失败**：保持 cancelling 状态，便于系统发现并重试

## 扣费时机

- **延迟扣费**：创建订阅（BillingService::createSubscription / tryAutoRenew / ApiService::createOrder）时不扣费，cert.amount='0.00'，purchased_count=0
- **首次扣费**：new-order 提交域名时按实际域名精确计费（OrderService::create / ApiService::createOrder）
- **action 判断**：purchased_standard_count==0 && purchased_wildcard_count==0 → action='new'（含基础价格），否则 → action='reissue'（只计增购）
- **幂等扣费**：通过 purchased count 判断是否需要增购，避免重复扣费

## 配置

```
# system_settings ca 组（优先使用专用配置，未设置时回落到通用配置）
acmeUrl  = ACME REST API 地址      ← 回落: url（路径 /api/v\w+ 替换为 /api/acme）
acmeToken = ACME API 认证 Token    ← 回落: token
```

## 安全机制

- **JWS 签名验证** - 支持 RS256/384/512、ES256/384/512
- **算法混淆防护** - 严格验证 alg 与密钥类型，EC 验证曲线
- **Nonce 防重放** - `Cache::pull()` 原子操作
- **EAB 强制要求** - 必须提供有效凭证
- **时序攻击防护** - HMAC 使用 `hash_equals()`

## 数据流

```
1. certbot register  → Manager /acme/new-acct  → 验证 EAB + JWS → 创建 AcmeAccount
2. certbot certonly  → Manager /acme/new-order → AcmeApiService.createOrder → 上级 REST API
3. Manager 返回 DNS challenge → 用户添加 _acme-challenge TXT 记录
4. certbot 通知验证  → Manager /acme/chall     → OrderService.respondToChallenge → 上级触发验证
5. 验证通过          → Manager /acme/order/.../finalize → OrderService.finalize → 上级签发（可能返回 processing）
5a. 如果 processing  → certbot 轮询 /acme/order/{referId} → OrderController.getOrder → tryCompletePendingFinalize 检查上游
6. certbot 下载证书  → Manager /acme/cert      → 返回证书 + 中间证书
```

## E2E 测试

完整链路：certbot (Docker) → Manager → 上级系统 → CA

验证要点：
- Manager `acme_certs` 表出现新记录
- 上级系统 `acme_certs` 表出现对应记录
- 完整流程：EAB → 注册 → 创建订单 → DNS 验证 → Finalize → 下载证书

详细操作步骤见 `skills/acme-e2e-test/SKILL.md`。
