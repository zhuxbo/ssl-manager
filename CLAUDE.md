# Manager Monorepo

## 文档原则

- **根目录 README**：给用户看，只写部署相关和简单系统架构说明
- **功能目录 README**：详细文档放在各自功能目录
  - `build/README.md` - 构建系统、版本发布
  - `develop/README.md` - 开发环境搭建
  - `deploy/docker/README.md` - Docker 部署详细说明
- **CLAUDE.md**：AI 助手参考，记录项目内部结构和开发约定

---

## 项目结构

```
frontend/
├── shared/     # 共享代码库（组件、工具、指令）
├── admin/      # 管理端应用
└── user/       # 用户端应用
backend/        # Laravel 11 后端
build/          # 构建系统（见 build/README.md）
deploy/         # 部署脚本
develop/        # 开发环境（见 develop/README.md）
```

## 共享包 (shared)

使用 `@shared/*` 别名访问：
- `@shared/components` - ReIcon, ReDialog, Auth, Perms, PureTableBar 等
- `@shared/utils` - http, auth, message 等
- `@shared/directives` - auth, perms, copy 等

shared 模块使用依赖注入，需在应用启动时初始化（见 `admin/src/utils/setup.ts`）。

## 工作流程

- **base 目录只读**：通过 git subtree 同步上游代码，不要修改
- **base 依赖**：本地开发需在 base 目录执行 `pnpm install --ignore-workspace`
- **不要自动提交**：完成修改后等待用户确认"提交"再执行 git commit/push

---

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

---

## 安装流程

### Docker 安装

```bash
# 一键安装（自动检测环境）
curl -fsSL https://release-cn.cnssl.com/install.sh | sudo bash

# 指定 Docker 模式
curl -fsSL https://release-cn.cnssl.com/install.sh | sudo bash -s -- docker

# 非交互式安装
curl -fsSL https://release-cn.cnssl.com/install.sh | sudo bash -s -- docker -y
```

Docker 部署目录结构：
```
/opt/ssl-manager/
├── backend/              # Laravel 后端代码
├── frontend/             # 前端静态文件
├── nginx/                # nginx 配置
├── data/
│   ├── version.json      # 版本配置（挂载到容器）
│   ├── mysql/            # MySQL 数据
│   ├── redis/            # Redis 数据
│   ├── storage/          # Laravel storage
│   └── logs/             # 日志
└── docker-compose.yml
```

容器挂载关系：
- `./backend:/var/www/html/backend` - 后端代码
- `./data/version.json:/var/www/html/data/version.json` - 版本配置
- `./data/storage:/var/www/html/backend/storage` - 存储目录

### 宝塔面板安装（两阶段）

| 阶段 | 执行者 | 职责 |
|------|--------|------|
| 环境准备 | `deploy/scripts/bt-install.sh` | PHP 版本选择、扩展检测、Composer 安装、代码下载、权限设置 |
| 应用安装 | `backend/public/install.php` | Composer 依赖安装、环境配置、数据库迁移、初始化 |

### Composer 依赖安装

Composer 依赖在 Web 安装向导中安装（`InstallExecutor.php` → `ComposerRunner.php`）。

网络检测优先级：
1. `FORCE_CHINA_MIRROR` 环境变量强制指定
2. 云服务商元数据检测（阿里云、腾讯云、华为云中国区域）
3. 百度可达 + Google 不可达检测
4. GitHub API 访问速度检测

中国大陆网络自动使用腾讯云 Composer 镜像。

---

## 在线升级

版本号在 `version.json` 配置，升级服务位于 `backend/app/Services/Upgrade/`。

```bash
php artisan upgrade:check              # 检查更新
php artisan upgrade:run                # 执行升级
php artisan upgrade:rollback           # 回滚
```

### 升级模式

系统支持两种升级模式：

| 特性 | PHP API 升级 | Shell 脚本升级 |
|------|-------------|---------------|
| 触发方式 | 管理后台 API | `deploy/upgrade.sh` |
| 升级包 | `upgrade` 包 | `full` 包 |
| 维护模式 | ✅ 自动进入/退出 | ✅ 自动进入/退出 |
| 权限修复 | ✅ 自动检测和修复 | ✅ 自动修复 |
| 适用环境 | Docker / 宝塔 | Docker / 宝塔 |

### 关键服务

- `UpgradeService` - 升级主逻辑，包含 `performUpgradeWithStatus()` 后台升级方法
- `UpgradeStatusManager` - 状态管理，支持动态步骤计算
- `PackageExtractor` - 包解压和应用，包含权限检查
- `ReleaseClient` - Release 获取，支持 Docker 容器内 localhost 地址转换
- `BackupManager` - 备份和恢复
- `VersionManager` - 版本比较和环境检测，支持 Docker/宝塔双模式

### version.json 路径

根据部署环境自动选择：

| 环境 | 路径 | 说明 |
|------|------|------|
| Docker | `/var/www/html/data/version.json` | 挂载到 data 目录确保可写 |
| 宝塔 | `/www/wwwroot/xxx/version.json` | 项目根目录 |

`VersionManager.isDockerEnvironment()` 检测方式：
1. 检查 `/.dockerenv` 文件存在
2. 检查 `/proc/1/cgroup` 包含 `docker` 或 `kubepods`

### Docker 容器内地址转换

`ReleaseClient` 在 Docker 环境下自动转换 localhost 地址：
- `http://localhost:10002` → `http://172.17.0.1:10002`（Linux Docker）
- `http://localhost:10002` → `http://host.docker.internal:10002`（Docker Desktop）

检测逻辑：检查 `/etc/hosts` 是否有 `host.docker.internal` 条目

### 自定义 Release URL

通过 `version.json` 的 `release_url` 字段配置自定义升级源：

```json
{
  "version": "0.0.9-beta",
  "channel": "dev",
  "release_url": "http://localhost:10002"
}
```

- 升级时 `release_url` 自动保留，不会被升级包覆盖
- 如果未配置，默认使用 Gitee 源

### 环境检测

升级系统自动检测部署环境：
- **Docker**: Web 用户 `www-data`，路径 `/var/www/html`
- **宝塔**: Web 用户 `www`，路径 `/www/wwwroot/*`

检测标志：
1. 存在 `/www/server` 目录
2. 存在 `www` 系统用户
3. 安装目录在 `/www/wwwroot/` 下

### 安装目录自动检测

升级脚本通过 `backend/.ssl-manager` 标记文件自动检测安装目录，按以下顺序搜索：

1. 预设目录快速检测：`/opt/ssl-manager`、`/opt/cert-manager`、`/www/wwwroot/ssl-manager`
2. 系统范围搜索（`/opt`、`/www/wwwroot`、`/home`，深度 4 层）

### 本地开发测试

使用本地 release 服务测试升级流程：

```bash
# 1. 构建并发布到本地 release 服务
cd build && ./local-release.sh

# 2. 脚本升级测试
sudo ./deploy/upgrade.sh --url http://localhost:10002 --version 0.0.10-beta --dir /path/to/install -y

# 3. 后台升级测试（需先配置 version.json 的 release_url）
```

Release 服务目录结构：
```
/www/wwwroot/dev/release.test/
├── releases.json     # Release 索引
├── main/            # 正式版
├── dev/             # 开发版
├── latest/          # 最新稳定版符号链接
└── dev-latest/      # 最新开发版符号链接
```

---

## Auto API

自动部署工具 API，通过 `refer_id` 认证：

```http
Authorization: Bearer <refer_id>
```

回调接口：`POST /api/auto/callback`
