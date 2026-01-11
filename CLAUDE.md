# Manager Monorepo

## ACME 模块

Manager 实现了 ACME RFC 8555 协议服务端，供 certbot 等 ACME 客户端使用。

### 架构

```
certbot → Manager (ACME 服务) → Gateway/上级 Manager (REST API) → Certum
```

### ACME 端点 (`/acme/*`)

- `GET /acme/directory` - 目录
- `HEAD/GET /acme/new-nonce` - 获取 Nonce
- `POST /acme/new-acct` - 注册账户（需要 EAB）
- `POST /acme/new-order` - 创建订单
- `POST /acme/authz/{token}` - 获取授权
- `POST /acme/chall/{token}` - 响应验证
- `POST /acme/order/{token}/finalize` - 完成订单
- `POST /acme/cert/{token}` - 下载证书

### REST API 端点 (`/api/acme/*`)

供下级 Manager 调用，与 Gateway 接口一致：

- `POST /api/acme/accounts` - 创建账户
- `POST /api/acme/orders` - 创建订单
- `GET /api/acme/orders/{id}` - 获取订单
- `POST /api/acme/orders/{id}/finalize` - 完成订单
- `GET /api/acme/orders/{id}/certificate` - 下载证书

### 关键服务

- `App\Services\Acme\JwsService` - JWS 解析和验证
- `App\Services\Acme\NonceService` - Nonce 管理
- `App\Services\Acme\AccountService` - 账户管理
- `App\Services\Acme\OrderService` - 订单管理
- `App\Services\Acme\BillingService` - 计费逻辑
- `App\Services\Acme\UpstreamClient` - 上级 API 调用

### 配置

```bash
# .env
ACME_GATEWAY_URL=https://gateway.example.com/api  # 必须配置，否则无法签发证书
ACME_GATEWAY_KEY=xxx
ACME_DEFAULT_PRODUCT_ID=xxx
```

### 安全机制

- **JWS 签名验证** - 所有 POST 请求需 JWS 签名，支持 RS256/384/512 和 ES256/384/512
- **算法混淆防护** - 严格验证 alg 与密钥类型匹配，EC 还验证曲线（P-256/384/521）
- **Nonce 防重放** - 使用 Redis `Cache::pull()` 原子操作，每个 Nonce 仅能使用一次
- **请求 URL 验证** - 防止 URL 混淆攻击
- **EAB 强制要求** - 必须提供有效的外部账户绑定凭证
- **时序攻击防护** - EAB HMAC 验证使用 `hash_equals()`

### 安全测试

```bash
php artisan test tests/Unit/AcmeSecurityTest.php
```

---

## 工作流程

- **base 目录只读**：通过 git subtree 同步上游代码，不要修改
- **base 依赖**：本地开发需在 base 目录执行 `pnpm install --ignore-workspace`

## 包结构

```
frontend/
├── shared/     # 共享代码库（组件、工具、指令、构建工具）
├── admin/      # 管理端应用
└── user/       # 用户端应用
backend/        # Laravel 11 后端
build/          # Docker 容器化构建
deploy/         # 部署脚本
```

## 共享包 (shared)

使用 `@shared/*` 别名访问：
- `@shared/components` - ReIcon, ReDialog, Auth, Perms, PureTableBar 等
- `@shared/utils` - http, auth, message 等
- `@shared/directives` - auth, perms, copy 等

shared 模块使用依赖注入，需在应用启动时初始化（见 `admin/src/utils/setup.ts`）。

## 开发命令

```bash
pnpm dev           # 同时启动 admin 和 user
pnpm dev:admin     # 仅启动 admin
pnpm build         # 构建所有前端
```

## 生产构建

```bash
cd build
./build.sh --test           # 测试构建
./build.sh --prod           # 生产构建（推送）
./build.sh --test admin     # 仅构建管理端
```

定制目录 `build/custom/` 存放 `build.env`、`config.json`、logo 等定制资源，不纳入版本控制。

## 开发环境

```bash
cd develop
./start.sh up      # 启动所有服务
./start.sh down    # 停止
./start.sh init    # 初始化（首次）
./start.sh logs    # 查看日志
```

服务端口：
- Admin: http://localhost:5201
- User: http://localhost:5202
- Backend: http://localhost:8001
- MySQL: 3306
- Redis: 6379

容器顺序启动：MySQL/Redis → Backend → Admin → User（Admin 健康后 User 才启动）

## 在线升级

版本号在 `version.json` 配置，升级服务位于 `backend/app/Services/Upgrade/`。

```bash
# artisan 命令
php artisan upgrade:check              # 检查更新
php artisan upgrade:run                # 执行升级
php artisan upgrade:rollback           # 回滚

# 脚本升级
deploy/upgrade.sh                      # 升级到最新版
deploy/upgrade.sh --version 1.0.0      # 指定版本
deploy/upgrade.sh rollback             # 回滚
deploy/upgrade.sh --dir /path -y       # 指定目录，自动确认
```

测试环境配置（使用本地 release-server）：
```bash
# backend/.env
UPGRADE_PROVIDER=local
UPGRADE_LOCAL_URL=http://release-server

# 启动 release-server
cd deploy/release-server && docker compose up -d
```

## 部署

```bash
# 自动检测环境
curl -fsSL https://gitee.com/zhuxbo/cert-manager/raw/main/deploy/install.sh | bash

# 指定 Docker/宝塔
bash -s docker  # 或 bash -s bt

# 强制使用国内 Composer 镜像
FORCE_CHINA_MIRROR=1 bash install.sh
```

脚本自动检测网络环境（云服务商元数据 + GitHub 可达性），决定是否使用阿里云镜像。

## Auto API

自动部署工具 API，通过 `refer_id` 认证：

```http
Authorization: Bearer <refer_id>
```

回调接口：`POST /api/auto/callback`
