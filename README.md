# cert-manager

[![GitHub Release](https://img.shields.io/github/v/release/zhuxbo/cert-manager?include_prereleases)](https://github.com/zhuxbo/cert-manager/releases)
[![CI](https://github.com/zhuxbo/cert-manager/actions/workflows/ci.yml/badge.svg)](https://github.com/zhuxbo/cert-manager/actions/workflows/ci.yml)

SSL 证书管理系统，支持多级代理、自动签发、在线升级。

## 安装

```bash
# 自动检测环境（推荐）
curl -fsSL https://gitee.com/zhuxbo/cert-manager/raw/main/deploy/install.sh | bash

# 指定部署方式
curl ... | bash -s docker   # Docker 部署
curl ... | bash -s bt       # 宝塔面板部署
```

| 方式 | 说明 |
|------|------|
| Docker | 推荐，7 步交互式配置，自动安装 Docker |
| 宝塔 | 使用宝塔管理 PHP/MySQL/Nginx |

## 升级

```bash
php artisan upgrade:check    # 检查更新
php artisan upgrade:run      # 执行升级
php artisan upgrade:rollback # 回滚
```

## 开发

```bash
# 前端
pnpm install
pnpm dev           # 启动 admin + user
pnpm dev:admin     # 仅启动管理端
pnpm build         # 构建所有前端

# 后端
cd backend
composer install
php artisan serve
```

## 生产构建

```bash
cd build
./build.sh --test           # 测试构建
./build.sh --prod           # 生产构建（推送）
./build.sh --test admin     # 仅构建管理端
```

## 架构

```
frontend/
├── shared/     # 共享组件库
├── admin/      # 管理端
└── user/       # 用户端
backend/        # Laravel 11 API
build/          # Docker 构建
deploy/         # 部署脚本
```

| 组件 | 技术栈 |
|------|--------|
| 后端 | Laravel 11, PHP 8.3+, MySQL, Redis |
| 前端 | Vue 3, TypeScript, Element Plus, Vite |
| 构建 | Docker 容器化，pnpm workspace |

## 自动化部署

### CNAME 委托

将域名验证 CNAME 记录指向平台托管域名，实现自动签发：

```
_acme-challenge.example.com  →  *******.your-platform.com
```

配置后，平台自动完成 DNS 验证，无需手动操作。

### 自动部署

配合 [cert-deploy](https://github.com/zhuxbo/cert-deploy) 工具实现全自动化：

```bash
# 安装部署工具
curl -fsSL https://gitee.com/zhuxbo/cert-deploy/raw/main/deploy/install.sh | sudo bash

# 扫描并生成配置
cert-deploy -scan
cert-deploy -init -url https://your-platform.com/api/auto/cert -refer_id <id>

# 部署证书
cert-deploy -site example.com
```

### Auto API

通过 `refer_id` 认证：

```http
GET  /api/auto/cert      # 获取证书
POST /api/auto/callback  # 部署回调

Authorization: Bearer <refer_id>
```

## License

MIT
