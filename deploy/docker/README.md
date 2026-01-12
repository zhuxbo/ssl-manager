# SSL Manager Docker 部署

## 方式一：脚本部署（推荐）

```bash
curl -fsSL https://gitee.com/zhuxbo/cert-manager/raw/main/deploy/install.sh | bash -s docker
```

脚本会引导您完成 7 步交互式配置。

## 方式二：手动部署

### 1. 下载完整包

```bash
# 下载最新版
curl -LO https://github.com/zhuxbo/cert-manager/releases/latest/download/ssl-manager-full-latest.zip
unzip ssl-manager-full-latest.zip
cd ssl-manager
```

### 2. 配置环境

```bash
# 复制配置文件
cp deploy/docker/.env.example .env
cp deploy/docker/docker-compose.example.yml docker-compose.yml
cp deploy/docker/Dockerfile Dockerfile
cp -r deploy/docker/config config/

# 生成密码并替换
DB_PASS=$(openssl rand -base64 12 | tr -dc 'a-zA-Z0-9')
sed -i "s/__DB_PASSWORD__/$DB_PASS/g" .env
```

### 3. 编辑配置

```bash
# 编辑 .env 根据需要修改端口、数据库等配置
vi .env
```

### 4. 启动服务

```bash
docker compose up -d
```

### 5. 初始化应用

```bash
# 安装 Composer 依赖
docker compose exec php composer install --no-dev --optimize-autoloader

# 运行数据库迁移
docker compose exec php php artisan migrate --force

# 优化应用
docker compose exec php php artisan config:cache
docker compose exec php php artisan route:cache
```

## 配置修改

部署完成后，如需修改配置：

| 文件 | 说明 | 修改后操作 |
|------|------|-----------|
| `.env` | 环境变量（端口、数据库等） | `docker compose up -d` |
| `docker-compose.yml` | 服务编排 | `docker compose up -d` |
| `Dockerfile` | PHP 镜像定义 | `docker compose build && docker compose up -d` |
| `config/site.conf` | Nginx 站点配置 | `docker compose restart nginx` |
| `config/nginx.conf` | Nginx 主配置 | `docker compose restart nginx` |
| `config/php.ini` | PHP 配置 | `docker compose restart php queue scheduler` |
| `backend/.env` | Laravel 应用配置 | `docker compose restart php queue scheduler` |

## 目录结构

```
/opt/ssl-manager/
├── .env                    # Docker 环境变量
├── docker-compose.yml      # Docker 编排配置
├── Dockerfile              # PHP 镜像定义
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
    ├── mysql/
    ├── redis/
    ├── storage/
    └── logs/
```

## 常用命令

```bash
# 查看服务状态
docker compose ps

# 查看日志
docker compose logs -f
docker compose logs -f php     # 仅 PHP 日志
docker compose logs -f nginx   # 仅 Nginx 日志

# 重启服务
docker compose restart

# 停止服务
docker compose down

# 停止并删除数据
docker compose down -v

# 进入 PHP 容器
docker compose exec php sh

# 执行 artisan 命令
docker compose exec php php artisan <command>
```

## 中国用户

如需使用国内镜像源，编辑 `Dockerfile`：

```dockerfile
# 取消注释以下行
# __ALPINE_MIRROR__
# __COMPOSER_MIRROR__

# 替换为：
RUN sed -i 's/dl-cdn.alpinelinux.org/mirrors.tencent.com/g' /etc/apk/repositories
RUN composer config -g repo.packagist composer https://mirrors.tencent.com/composer/
```

然后重新构建：

```bash
docker compose build --no-cache
docker compose up -d
```
