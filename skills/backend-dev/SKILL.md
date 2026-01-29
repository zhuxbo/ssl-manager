# 后端开发规范

## 技术栈

- **框架**: Laravel 11.x
- **PHP**: 8.3+（双引号变量不加大括号）
- **数据库**: MySQL 8.0
- **缓存/队列**: Redis
- **认证**: JWT (tymon/jwt-auth)
- **代码规范**: PSR-12、PHP Pint、PHPStan

## 目录结构

```
backend/
├── app/
│   ├── Http/Controllers/
│   │   ├── User/           # 用户端 /api/*
│   │   ├── Admin/          # 管理端 /api/admin/*
│   │   ├── V1/             # API v1
│   │   ├── V2/             # API v2
│   │   └── Callback/       # 回调处理
│   ├── Models/
│   ├── Services/           # 业务逻辑层
│   │   ├── Acme/          # ACME 协议服务
│   │   ├── Order/         # 订单服务
│   │   └── Upgrade/       # 升级系统
│   ├── Jobs/               # 队列任务
│   └── Utils/
├── routes/
│   ├── api.user.php
│   ├── api.admin.php
│   ├── api.v1.php
│   ├── api.v2.php
│   └── api.callback.php
└── database/
    ├── migrations/
    └── seeders/
```

## 架构设计

### 纯 API 架构

- 无 session/cookie 依赖，完全前后端分离
- 统一响应格式：成功 `{"code": 1, "data": {...}}`，失败 `{"code": 0, "msg": "..."}`
- 统一异常处理：`ApiResponseException`

### JWT 多端认证

| 端 | 路由前缀 | 认证方式 |
|----|---------|---------|
| 用户端 | `/api/` | JWT |
| 管理端 | `/api/admin/` | JWT |
| API v1/v2 | `/api/V1/`, `/api/v2/` | Token |

---

## ACME 模块

实现 ACME RFC 8555 协议服务端，供 certbot 等客户端使用。

### 架构

```
certbot → Manager (ACME 服务) → Gateway/上级 Manager (REST API) → Certum
```

### ACME 端点 (`/acme/*`)

| 方法 | 端点 | 功能 |
|------|------|------|
| GET | `/acme/directory` | 目录 |
| HEAD/GET | `/acme/new-nonce` | 获取 Nonce |
| POST | `/acme/new-acct` | 注册账户（需 EAB） |
| POST | `/acme/new-order` | 创建订单 |
| POST | `/acme/authz/{token}` | 获取授权 |
| POST | `/acme/chall/{token}` | 响应验证 |
| POST | `/acme/order/{token}/finalize` | 完成订单 |
| POST | `/acme/cert/{token}` | 下载证书 |

### REST API 端点 (`/api/acme/*`)

供下级 Manager 调用：

- `POST /api/acme/accounts` - 创建账户
- `POST /api/acme/orders` - 创建订单
- `GET /api/acme/orders/{id}` - 获取订单
- `POST /api/acme/orders/{id}/finalize` - 完成订单
- `GET /api/acme/orders/{id}/certificate` - 下载证书

### 关键服务

| 服务 | 职责 |
|------|------|
| `JwsService` | JWS 解析和验证 |
| `NonceService` | Nonce 管理（Redis Cache::pull 原子操作） |
| `AccountService` | 账户管理 |
| `OrderService` | 订单管理 |
| `BillingService` | 计费逻辑 |
| `UpstreamClient` | 上级 API 调用 |

### 配置

```bash
# .env
ACME_GATEWAY_URL=https://gateway.example.com/api
ACME_GATEWAY_KEY=xxx
ACME_DEFAULT_PRODUCT_ID=xxx
```

### 安全机制

- **JWS 签名验证** - 支持 RS256/384/512、ES256/384/512
- **算法混淆防护** - 严格验证 alg 与密钥类型，EC 验证曲线
- **Nonce 防重放** - `Cache::pull()` 原子操作
- **EAB 强制要求** - 必须提供有效凭证
- **时序攻击防护** - HMAC 使用 `hash_equals()`

---

## 升级系统

### 关键服务

| 服务 | 职责 |
|------|------|
| `UpgradeService` | 升级主逻辑，`performUpgradeWithStatus()` |
| `UpgradeStatusManager` | 状态管理，动态步骤计算 |
| `PackageExtractor` | 包解压和应用，权限检查 |
| `ReleaseClient` | Release 获取，Docker 地址转换 |
| `BackupManager` | 备份和恢复 |
| `VersionManager` | 版本比较，环境检测 |

### 升级模式

| 特性 | PHP API 升级 | Shell 脚本升级 |
|------|-------------|---------------|
| 触发方式 | 管理后台 API | `deploy/upgrade.sh` |
| 升级包 | `upgrade` 包 | `full` 包 |
| 维护模式 | 自动进入/退出 | 自动进入/退出 |

### 环境检测

```php
// VersionManager.isDockerEnvironment()
// 1. 检查 /.dockerenv 文件
// 2. 检查 /proc/1/cgroup 包含 docker/kubepods
```

| 环境 | Web 用户 | version.json 路径 |
|------|---------|------------------|
| Docker | www-data | `/var/www/html/data/version.json` |
| 宝塔 | www | 项目根目录 |

---

## 代码规范

### 命令

```bash
./vendor/bin/pint         # 代码格式化
./vendor/bin/phpstan analyse  # 静态分析
php artisan test          # 测试
```

### 开发流程

1. 遵循功能优先开发
2. PSR-12 编码规范
3. 统一异常处理
4. 分类日志记录

---

## 常用 Artisan 命令

```bash
php artisan upgrade:check     # 检查更新
php artisan upgrade:run       # 执行升级
php artisan upgrade:rollback  # 回滚
php artisan queue:work --queue Task  # 队列
```

---

## 缓存与日志架构

### 缓存驱动
- 默认使用 `file` 驱动，不强制依赖 Redis
- 生产环境推荐使用 Redis 提升性能
- SnowFlake、RateLimiter 通过 `Cache` facade 操作

### 日志批量写入
- `LogBuffer` 服务：收集请求期间的日志，请求结束后批量写入
- `FlushLogs` 中间件：响应发送后触发日志刷入
- 所有日志模型使用默认数据库连接

---

## Token 认证体系

| Token 类型 | 中间件 | 路由前缀 | 用途 |
|-----------|--------|---------|------|
| ApiToken | `api.v1` / `api.v2` | `/api/v1/`, `/api/v2/` | 第三方 API 调用 |
| DeployToken | `api.deploy` | `/api/deploy/` | 部署工具证书管理 |

### 共同特性
- Token 使用 SHA-256 hash 存储
- 支持 IP 白名单（最多 100 个，`allowed_ips` 字段 2000 字符）
- 支持速率限制（`rate_limit` 字段，每分钟请求数）
- 请求结束后异步更新 `last_used_at` 和 `last_ip`

### DeployToken 特性
- 每个用户仅一个 DeployToken（唯一约束）
- 通过 `UserScope` 限制只能访问用户自己的 Order
- 支持查询证书、续费/重签、部署回调

---

## MySQL 兼容性

- 兼容 MySQL 5.7，不使用 `json` 字段类型
- 数组类型字段使用 `string` 存储，由 Laravel 模型 `'array'` cast 自动 JSON 序列化

---

## 自动续费/重签

### 数据结构

| 字段 | 位置 | 说明 |
|------|------|------|
| `auto_renew` | orders 表 | 订单级自动续费开关 |
| `auto_reissue` | orders 表 | 订单级自动重签开关 |
| `auto_settings` | users 表 | 用户级默认设置 JSON |

### 回落逻辑

订单设置为 `null` 时回落到用户设置：
```php
->where(function ($query) {
    $query->where('auto_renew', true)
        ->orWhere(function ($q) {
            $q->whereNull('auto_renew')
              ->whereHas('user', fn ($u) => $u->where('auto_settings->auto_renew', true));
        });
})
```

### 续费 vs 重签判断

- `period_till - expires_at < 7天`：续费（订单周期与证书到期接近）
- `period_till - expires_at > 7天`：重签（订单周期内还有余量）

### 相关命令

`php artisan schedule:auto-renew` - 同时处理续费和重签

---

## 委托验证

### 验证方法转换

用户选择 `delegate` 验证方法时：
1. `ActionTrait::generateDcv()` 将 method 转换为 `txt`
2. 设置 `dcv['is_delegate'] = true` 标记
3. `generateValidation()` 查找用户的 CnameDelegation 记录
4. validation 数组包含 `delegation_id`、`delegation_target`、`delegation_valid`

### 委托前缀

| 前缀 | CA | 匹配规则 |
|------|-----|---------|
| `_acme-challenge` | ACME | 严格子域匹配 |
| `_dnsauth` | DigiCert、TrustAsia | 严格子域匹配 |
| `_pki-validation` | Sectigo | 优先子域，回落根域 |
| `_certum` | Certum | 优先子域，回落根域 |

### 即时检测

`ValidateCommand::checkDelegationValidity()` 在验证前即时检测委托记录状态。

### DCV 数据合并

从上游 API 更新 dcv 时必须保留委托标记，使用 `ActionTrait::mergeDcv()` 方法：

```php
// 保留 is_delegate 和 ca 标记
$cert->dcv = $this->mergeDcv($result['data']['dcv'] ?? null, $cert->dcv);
```

涉及位置（`Action.php`）：
- 提交订单后更新 dcv
- 同步订单时更新 dcv
- 修改验证方法时（processing 状态）

### 前端判断逻辑

`validation.vue` 的 `getDisplayMethod()` 根据 `dcv.is_delegate` 返回验证方法：
```javascript
const getDisplayMethod = (dcv) => {
  if (dcv?.is_delegate) return "delegation";
  return dcv?.method;
};
```

### 相关服务

- `CnameDelegationService::findValidDelegation()` - 智能匹配委托记录
- `CnameDelegationService::checkAndUpdateValidity()` - 检测并更新有效性
- `AutoDcvTxtService` - 自动写入 TXT 记录
