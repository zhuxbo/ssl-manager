# SSL Manager

[![GitHub Release](https://img.shields.io/github/v/release/zhuxbo/cert-manager?include_prereleases)](https://github.com/zhuxbo/cert-manager/releases)
[![CI](https://github.com/zhuxbo/cert-manager/actions/workflows/ci.yml/badge.svg)](https://github.com/zhuxbo/cert-manager/actions/workflows/ci.yml)

SSL 证书管理系统，支持多级代理、自动签发、在线升级。

## 安装

```bash
curl -fsSL http://release.example.com/install.sh | bash
```

<details>
<summary>更多安装选项</summary>

```bash
# 指定部署方式
curl ... | bash -s docker   # Docker 部署
curl ... | bash -s bt       # 宝塔面板部署

# 指定版本安装
curl ... | bash -s -- --version 0.0.10-beta
```

| 参数 | 说明 |
|------|------|
| `docker` | Docker 部署（推荐） |
| `bt` | 宝塔面板部署 |
| `--version latest` | 最新稳定版（默认） |
| `--version dev` | 最新开发版 |
| `--version x.x.x` | 指定版本号 |

</details>

## 升级

### 在线升级（推荐）

登录管理后台 → 系统设置 → 在线升级，可视化操作。

### 脚本升级

```bash
curl -fsSL http://release.example.com/upgrade.sh | bash
```

<details>
<summary>更多升级选项</summary>

```bash
curl ... | bash                         # 升级到最新版
curl ... | bash -s -- --version 1.0.0   # 升级到指定版本
curl ... | bash -s -- --dir /path/to/app  # 指定安装目录
curl ... | bash -s -- rollback          # 回滚到上一版本

# artisan 命令（需进入 backend 目录）
php artisan upgrade:check       # 检查更新
php artisan upgrade:run         # 执行升级
php artisan upgrade:rollback    # 回滚
```

| 参数 | 说明 |
|------|------|
| `--version x.x.x` | 升级到指定版本 |
| `--dir PATH` | 指定安装目录（自动检测失败时使用） |
| `-y, --yes` | 自动确认，非交互模式 |
| `rollback` | 回滚到上一版本 |

</details>

## 卸载

Docker 部署卸载：

```bash
cd /opt/ssl-manager  # 进入安装目录
docker-compose down -v  # 停止并删除容器和数据卷
```

## 架构

```
frontend/           # Vue 3 前端
├── shared/         # 共享组件库
├── admin/          # 管理端
└── user/           # 用户端
backend/            # Laravel 11 后端
build/              # 构建系统（见 build/README.md）
deploy/             # 部署脚本
develop/            # 开发环境（见 develop/README.md）
```

| 组件 | 技术栈 |
|------|--------|
| 后端 | Laravel 11, PHP 8.3+, MySQL, Redis |
| 前端 | Vue 3, TypeScript, Element Plus, Vite |

## 自动化部署

### CNAME 委托

将域名验证 CNAME 记录指向平台托管域名，实现自动签发：

```
_acme-challenge.example.com  →  *******.your-platform.com
```

配置后，平台自动完成 DNS 验证，无需手动操作。

### 自动部署工具

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

## 文档

| 文档 | 说明 |
|------|------|
| [build/README.md](build/README.md) | 构建系统、版本发布 |
| [develop/README.md](develop/README.md) | 开发环境搭建 |
| [deploy/docker/README.md](deploy/docker/README.md) | Docker 部署详细说明 |

## License

MIT
