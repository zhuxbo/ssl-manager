# 部署运维规范

## 部署方式

| 方式 | 适用场景 | 安装命令 |
|------|---------|---------|
| Docker | 推荐，一键部署 | `curl -fsSL .../install.sh \| bash -s docker` |
| 宝塔面板 | 传统服务器 | `curl -fsSL .../install.sh \| bash` |

---

## Docker 部署

### 一键安装

```bash
# 自动检测环境
curl -fsSL https://release-cn.cnssl.com/install.sh | sudo bash

# 指定 Docker 模式
curl -fsSL https://release-cn.cnssl.com/install.sh | sudo bash -s -- docker

# 非交互式
curl -fsSL https://release-cn.cnssl.com/install.sh | sudo bash -s -- docker -y
```

### 目录结构

```
/opt/ssl-manager/
├── .env                    # Docker 环境变量
├── docker-compose.yml
├── Dockerfile
├── backend/                # Laravel 后端
│   └── .env               # 应用环境变量
├── frontend/               # 前端构建产物
│   ├── admin/
│   ├── user/
│   └── easy/
├── config/                 # 配置文件
│   ├── nginx.conf
│   ├── site.conf
│   └── php.ini
└── data/                   # 持久化数据
    ├── version.json       # 版本配置（挂载到容器）
    ├── mysql/
    ├── redis/
    ├── storage/           # Laravel storage
    └── logs/
```

### 容器挂载

```yaml
volumes:
  - ./backend:/var/www/html/backend
  - ./data/version.json:/var/www/html/data/version.json
  - ./data/storage:/var/www/html/backend/storage
```

### 常用命令

```bash
docker compose ps              # 查看状态
docker compose logs -f         # 查看日志
docker compose logs -f php     # PHP 日志
docker compose restart         # 重启
docker compose down            # 停止
docker compose exec php sh     # 进入容器
docker compose exec php php artisan <cmd>  # Artisan
```

### 配置修改

| 文件 | 说明 | 修改后操作 |
|------|------|-----------|
| `.env` | Docker 环境变量 | `docker compose up -d` |
| `docker-compose.yml` | 服务编排 | `docker compose up -d` |
| `Dockerfile` | PHP 镜像 | `docker compose build && up -d` |
| `config/site.conf` | Nginx 站点 | `docker compose restart nginx` |
| `backend/.env` | Laravel 配置 | `docker compose restart php queue scheduler` |

---

## 宝塔面板部署

### 两阶段安装

| 阶段 | 执行者 | 职责 |
|------|--------|------|
| 环境准备 | `deploy/scripts/bt-install.sh` | PHP 版本、扩展、Composer、代码下载、权限 |
| 应用安装 | `backend/public/install.php` | Composer 依赖、环境配置、数据库迁移、初始化 |

### 系统要求

- PHP 8.3+
- MySQL 5.7+
- Redis
- Composer 2.8+

### PHP 扩展

必需：bcmath, calendar, fileinfo, gd, iconv, intl, json, openssl, pcntl, pdo, redis, zip, mbstring

### PHP 函数

**必须启用**：exec, putenv, pcntl_signal, pcntl_alarm
**推荐启用**：proc_open（提升 Composer 性能）

### 权限设置

```bash
chmod -R 775 storage bootstrap/cache
chown -R $USER:www-data storage bootstrap/cache
```

---

## Composer 依赖安装

在 Web 安装向导中安装（`InstallExecutor.php` → `ComposerRunner.php`）。

### 网络检测优先级

1. `FORCE_CHINA_MIRROR` 环境变量
2. 云服务商元数据（阿里云、腾讯云、华为云中国区）
3. 百度可达 + Google 不可达
4. GitHub API 访问速度

中国大陆自动使用腾讯云 Composer 镜像。

---

## 环境检测

### Docker 检测

`VersionManager.isDockerEnvironment()`:
1. 检查 `/.dockerenv` 文件
2. 检查 `/proc/1/cgroup` 包含 `docker` 或 `kubepods`

### 宝塔检测

1. 存在 `/www/server` 目录
2. 存在 `www` 系统用户
3. 安装目录在 `/www/wwwroot/` 下

### 环境差异

| 项目 | Docker | 宝塔 |
|------|--------|------|
| Web 用户 | www-data | www |
| 安装路径 | /var/www/html | /www/wwwroot/* |
| version.json | /var/www/html/data/ | 项目根目录 |

---

## 升级

### 升级模式

| 特性 | PHP API 升级 | Shell 脚本升级 |
|------|-------------|---------------|
| 触发方式 | 管理后台 API | `deploy/upgrade.sh` |
| 升级包 | upgrade 包 | full 包 |
| 适用环境 | Docker / 宝塔 | Docker / 宝塔 |

### 命令

```bash
php artisan upgrade:check     # 检查更新
php artisan upgrade:run       # 执行升级
php artisan upgrade:rollback  # 回滚
```

### 安装目录检测

升级脚本通过 `backend/.ssl-manager` 标记文件检测：
1. 预设目录：/opt/ssl-manager、/opt/cert-manager、/www/wwwroot/ssl-manager
2. 系统搜索：/opt、/www/wwwroot、/home（深度 4 层）

---

## 常见问题

### 500 服务器错误
- 检查 `storage/logs` 日志
- 确保 PHP 扩展已安装

### Redis 连接失败
- 检查 Redis 服务
- 验证 .env 配置

### 权限问题
- 检查 storage、bootstrap/cache 可写
- 检查 web 用户权限

### PHP 函数被禁用
- 检查 `disable_functions` 设置
- 宝塔：PHP 管理 → 禁用函数
