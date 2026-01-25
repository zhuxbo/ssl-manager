#!/bin/bash
# Manager Docker 开发环境启动脚本

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# 导出 UID/GID 解决权限问题
export DOCKER_UID=$(id -u)
export DOCKER_GID=$(id -g)

# 如果 .env 不存在，从 .env.example 复制
if [ ! -f .env ]; then
    cp .env.example .env
    echo "已创建 .env 文件，请根据需要修改配置"
fi

# 加载环境变量
source .env

show_help() {
    echo "Manager Docker 开发环境"
    echo ""
    echo "用法: $0 <command>"
    echo ""
    echo "命令:"
    echo "  up        启动所有服务"
    echo "  down      停止所有服务"
    echo "  restart   重启所有服务"
    echo "  logs      查看日志"
    echo "  ps        查看服务状态"
    echo "  build     重新构建镜像"
    echo "  init      初始化项目（安装依赖、迁移数据库）"
    echo "  shell     进入后端容器"
    echo "  mysql     进入 MySQL 客户端"
    echo ""
    echo "服务端口:"
    echo "  后端 API:    http://localhost:${BACKEND_PORT:-8001}"
    echo "  Admin 前端:  http://localhost:${ADMIN_PORT:-5201}"
    echo "  User 前端:   http://localhost:${USER_PORT:-5202}"
}

case "$1" in
    up)
        echo "启动服务..."
        docker compose up -d
        echo ""
        echo "服务已启动:"
        echo "  后端 API:    http://localhost:${BACKEND_PORT:-8001}"
        echo "  Admin 前端:  http://localhost:${ADMIN_PORT:-5201}"
        echo "  User 前端:   http://localhost:${USER_PORT:-5202}"
        ;;
    down)
        echo "停止服务..."
        docker compose down
        ;;
    restart)
        echo "重启服务..."
        docker compose restart
        ;;
    logs)
        docker compose logs -f "${@:2}"
        ;;
    ps)
        docker compose ps
        ;;
    build)
        echo "重新构建镜像..."
        docker compose build --no-cache
        ;;
    init)
        echo "初始化项目..."
        # 启动基础服务（Redis 可选，默认不启动）
        docker compose up -d mysql
        echo "等待数据库就绪..."
        sleep 10

        # 启动后端并初始化
        docker compose up -d backend
        sleep 5

        echo "安装 Composer 依赖..."
        docker compose exec backend composer install

        echo "生成应用密钥..."
        docker compose exec backend php artisan key:generate --force
        docker compose exec backend php artisan jwt:secret --force

        echo "运行数据库迁移..."
        docker compose exec backend php artisan migrate --force

        echo "启动所有服务..."
        docker compose up -d

        echo ""
        echo "初始化完成！"
        ;;
    shell)
        docker compose exec backend bash
        ;;
    mysql)
        docker compose exec mysql mysql -uroot -p${MYSQL_ROOT_PASSWORD:-root123456} ${MYSQL_DATABASE:-manager}
        ;;
    *)
        show_help
        ;;
esac
