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
