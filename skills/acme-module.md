---
description: ACME 模块 - 封装下单 + 交付 EAB 模式、订阅计费、取消流程。修改 ACME 相关代码时自动加载。
---

# ACME 模块

Manager 作为 ACME 订阅管理平台，通过 REST API 连接 Gateway，向用户交付 EAB 凭据（eab_kid + eab_hmac）。

## 架构

```
用户/Deploy API → Manager (ACME 订阅) → Gateway REST API → Certum
```

简化为"封装下单 + 交付 EAB"模式，不再实现 RFC 8555 协议服务端。

## 数据模型

- 单一 `Acme` 模型（`App\Models\Acme`，表 `acmes`），替代旧的 `acme_orders`/`acme_certs`/`acme_authorizations` 多表
- `eab_hmac` 加密存储（`encrypted` cast），默认 hidden
- `products.product_type = 'acme'`（`Product::TYPE_ACME`）标识 ACME 产品
- Transaction 类型：`acme_order`（下单扣费）/ `acme_cancel`（取消退费）

## 状态流转

```
unpaid ──[pay]──→ pending ──[commit]──→ active ──[到期]──→ expired
   │                │                      │
   │                │                      └──[commitCancel]──→ cancelling ──[cancel 成功]──→ cancelled + 退费
   │                │                                              │
   │                │                                              ├──[上游返回 revoked]──→ revoked + 退费
   │                │                                              └──[上游失败]──→ 保持 cancelling（Job 重试）
   │                │
   │                └──[commitCancel, 无 api_id]──→ cancelled + 直接退费
   │
   └──[前端删除]──→ 删除记录（未支付无需退费）
```

## 计费流程（Action）

三步流程：

1. **`new`**（创建 unpaid 订单）：计算金额，不扣费
2. **`pay`**（支付 → pending）：扣费 + 创建 `acme_order` 交易记录
3. **`commit`**（提交 Gateway → active）：调 `Api->new()`，成功后写入 `api_id`/`eab_kid`/`eab_hmac`/`period_from`/`period_till`，状态 → active。**失败保持 pending，不退费**（用户可重试或取消）

## 取消流程

1. **`commitCancel`**：
   - 无 `api_id` 的 pending 订单 → 直接退费 + 标记 cancelled
   - 有 `api_id` 的订单 → 标记 cancelling + 创建 Task（action=`cancel_acme`，延迟 120s）+ dispatch `TaskJob`（延迟 123s）
2. **`cancel`**（由 TaskJob 调用）：
   - 调 `Api->cancel()` → 上游返回 revoked → 状态 revoked + 退费
   - 调 `Api->cancel()` → 上游返回其他成功 → 状态 cancelled + 退费
   - 上游失败 → 保持 cancelling，不退费（等待下次重试）

## API 层架构

`Services/Acme/Api/Api.php` — 路由器，按 `product.source` 分发：

- `certum/Api.php` → `certum/Sdk.php`（HTTP 调用 Gateway）
- `certumcnssl/Api.php`（继承 certum）
- `certumtest/Api.php`（继承 certum）

统一接口 `AcmeSourceApiInterface`：`new`/`get`/`cancel`

Gateway 端点（RPC 风格，通过 `order_id` 传参）：
- `POST /api/acme/new` — 创建订单
- `GET /api/acme/get?order_id=` — 查询订单
- `POST /api/acme/sync` — 同步订单
- `POST /api/acme/cancel` — 取消订单

## 控制器端点

### Admin（`/api/admin/acme/`）

| 方法 | 端点 | 功能 |
|------|------|------|
| GET | `/acme` | 列表（支持 user_id/brand/status 筛选） |
| GET | `/acme/{id}` | 详情（含 EAB） |
| POST | `/acme/new` | 创建订单 |
| POST | `/acme/pay/{id}` | 支付 |
| POST | `/acme/commit/{id}` | 提交 Gateway |
| POST | `/acme/sync/{id}` | 同步状态（status 白名单校验） |
| POST | `/acme/commit-cancel/{id}` | 取消 |
| POST | `/acme/remark/{id}` | 管理员备注 |

### User（`/api/acme/`）

与 Admin 类似但限当前用户，无 remark 端点。

### Deploy（`/api/deploy/acme/`）

- `POST /new` — 一步到位：创建 + 支付 + 提交
- `GET /get/{id}` — 获取详情（含 EAB）
