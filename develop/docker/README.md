# Manager Docker 开发环境

## 快速开始

```bash
# 首次使用
./start.sh init

# 日常启动
./start.sh up

# 停止
./start.sh down
```

## 服务端口

| 服务 | 端口 | 说明 |
|------|------|------|
| 后端 API | 8001 | `php artisan serve` |
| Admin 前端 | 5173 | Vite dev server |
| User 前端 | 5174 | Vite dev server |
| MySQL | 3306 | 数据库 |
| Redis | 6379 | 缓存 |

## 命令说明

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

## 配置

复制 `.env.example` 为 `.env` 并根据需要修改端口配置。

## 联调模式

本环境使用共享网络 `cnssl-dev-network`，可与 Gateway 联调：

```bash
# 启动 Manager
cd manager/develop/docker && ./start.sh up

# 启动 Gateway Backend（另一个终端）
cd gateway-backend/develop/docker && ./start.sh up
```

服务间可通过容器名互相访问：

- Manager Backend: `manager-backend:8000`
- Gateway Backend: `gateway-backend:8000`
