# 开发环境

## 本地环境

### 环境要求

- PHP 8.3+
- Node.js 22+
- pnpm 9+
- MySQL 8.0+
- Redis 7+
- Composer

### 后端

```bash
cd backend

# 安装依赖
composer install

# 配置环境
cp .env.example .env
php artisan key:generate

# 配置数据库（编辑 .env）
DB_HOST=127.0.0.1
DB_DATABASE=ssl_manager
DB_USERNAME=root
DB_PASSWORD=your_password

# 数据库迁移
php artisan migrate

# 启动服务
php artisan serve --port=8001
```

### 前端

```bash
# 安装依赖
pnpm install

# 启动开发服务器
pnpm dev           # 同时启动 admin + user
pnpm dev:admin     # 仅管理端 (http://localhost:5173)
pnpm dev:user      # 仅用户端 (http://localhost:5174)

# 构建
pnpm build         # 构建所有前端
pnpm build:admin   # 仅构建管理端
```

### 服务端口

| 服务 | 端口 | 说明 |
|------|------|------|
| 后端 API | 8001 | `php artisan serve` |
| Admin 前端 | 5173 | Vite dev server |
| User 前端 | 5174 | Vite dev server |

---

## Docker 环境

使用 Docker Compose 一键启动完整开发环境，无需本地安装 PHP/MySQL/Redis。

### 快速开始

```bash
cd develop

# 首次使用（初始化数据库、安装依赖）
./start.sh init

# 日常启动
./start.sh up

# 停止
./start.sh down
```

### 命令说明

```bash
./start.sh up        # 启动所有服务
./start.sh down      # 停止所有服务
./start.sh restart   # 重启服务
./start.sh logs      # 查看日志
./start.sh ps        # 查看状态
./start.sh build     # 重新构建镜像
./start.sh init      # 初始化项目
./start.sh shell     # 进入后端容器
./start.sh mysql     # 进入 MySQL 客户端
```

### 服务端口

| 服务 | 端口 | 说明 |
|------|------|------|
| 后端 API | 8001 | PHP 容器 |
| Admin 前端 | 5173 | Vite dev server |
| User 前端 | 5174 | Vite dev server |
| MySQL | 3306 | 数据库 |
| Redis | 6379 | 缓存 |

### 配置

复制 `.env.example` 为 `.env` 并根据需要修改端口配置。

### 联调模式

本环境使用共享网络 `cnssl-dev-network`，可与 Gateway 联调：

```bash
# 启动 Manager
cd manager/develop && ./start.sh up

# 启动 Gateway Backend（另一个终端）
cd gateway-backend/develop && ./start.sh up
```

服务间可通过容器名互相访问：

- Manager Backend: `manager-backend:8000`
- Gateway Backend: `gateway-backend:8000`

---

## 项目结构

```
frontend/
├── shared/         # 共享组件库（@shared/*）
├── admin/          # 管理端应用
└── user/           # 用户端应用
backend/            # Laravel 11 后端
```

### 共享包

使用 `@shared/*` 别名访问共享模块：

```typescript
import { ReIcon } from '@shared/components'
import { http } from '@shared/utils'
import { auth } from '@shared/directives'
```

共享模块使用依赖注入，需在应用启动时初始化（见 `admin/src/utils/setup.ts`）。

## 常见问题

### 前端热更新不生效

检查 Vite 配置中的 `server.watch` 选项，确保监听了正确的文件。

### 后端报错 Redis 连接失败

确保 Redis 服务已启动：
- 本地环境：`redis-server`
- Docker 环境：`./start.sh up`

### 数据库迁移失败

检查数据库连接配置，确保 MySQL 服务已启动且用户有创建表的权限。
