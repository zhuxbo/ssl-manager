#!/bin/bash

# SSL证书管理系统 - 升级脚本

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEPLOY_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

# 加载公共函数
source "$SCRIPT_DIR/common.sh"

# 全局变量
DEPLOY_MODE="${1:-}"  # docker 或空（宝塔）
UPGRADE_FILE=""
TARGET_VERSION=""

# 显示帮助
show_help() {
    cat <<EOF
SSL证书管理系统 - 升级脚本

用法: $0 [模式] [选项]

模式:
  (空)              宝塔环境升级
  docker            Docker 环境升级

选项:
  check             仅检查更新
  rollback          回滚到上一版本
  --version VER     升级到指定版本
  --file FILE       使用本地升级包
  -h, help          显示帮助

环境变量:
  FORCE_CHINA_MIRROR=1    强制使用国内 Composer 镜像
  FORCE_CHINA_MIRROR=0    强制使用国际源

示例:
  $0 check                    # 检查更新
  $0                          # 执行升级
  $0 --version 1.0.1          # 升级到指定版本
  $0 --file /path/to/pkg.zip  # 使用本地包升级
  $0 rollback                 # 回滚
  $0 docker                   # Docker 环境升级
  FORCE_CHINA_MIRROR=1 $0     # 强制使用国内镜像升级

EOF
    exit 0
}

# 解析参数
parse_args() {
    while [ $# -gt 0 ]; do
        case "$1" in
            -h|help|--help)
                show_help
                ;;
            docker)
                DEPLOY_MODE="docker"
                shift
                ;;
            check)
                ACTION="check"
                shift
                ;;
            rollback)
                ACTION="rollback"
                shift
                ;;
            --version)
                TARGET_VERSION="$2"
                shift 2
                ;;
            --file)
                UPGRADE_FILE="$2"
                shift 2
                ;;
            *)
                shift
                ;;
        esac
    done
}

# 检测安装目录
detect_install_dir() {
    # Docker 模式
    if [ "$DEPLOY_MODE" = "docker" ]; then
        if [ -f "$DEPLOY_DIR/../docker-compose.yml" ]; then
            INSTALL_DIR="$(cd "$DEPLOY_DIR/.." && pwd)"
        elif [ -f "/opt/ssl-manager/docker-compose.yml" ]; then
            INSTALL_DIR="/opt/ssl-manager"
        else
            log_error "未找到 Docker 安装目录"
            exit 1
        fi
        return
    fi

    # 宝塔模式：查找 backend 目录
    local possible_dirs=(
        "$DEPLOY_DIR/.."
        "/www/wwwroot/ssl-manager"
        "/www/wwwroot/cert-manager"
    )

    for dir in "${possible_dirs[@]}"; do
        if [ -d "$dir/backend" ] && [ -f "$dir/backend/artisan" ]; then
            INSTALL_DIR="$(cd "$dir" && pwd)"
            return
        fi
    done

    log_error "未找到安装目录"
    exit 1
}

# 获取当前版本
get_current_version() {
    local version_file="$INSTALL_DIR/backend/config/version.php"

    if [ -f "$version_file" ]; then
        grep -oP "(?<='version' => ')[^']+" "$version_file" 2>/dev/null || echo "unknown"
    else
        echo "unknown"
    fi
}

# 检查更新
check_update() {
    log_step "检查更新"

    local current_version=$(get_current_version)
    log_info "当前版本: $current_version"

    # 调用后端 API 检查更新
    if [ "$DEPLOY_MODE" = "docker" ]; then
        local compose_cmd=$(check_docker_compose)
        cd "$INSTALL_DIR"
        local result=$($compose_cmd exec -T php php artisan upgrade:check 2>/dev/null || echo "{}")
    else
        local php_cmd=$(find /www/server/php -name "php" -path "*/bin/*" | head -1)
        if [ -z "$php_cmd" ]; then
            php_cmd="php"
        fi
        cd "$INSTALL_DIR/backend"
        local result=$($php_cmd artisan upgrade:check 2>/dev/null || echo "{}")
    fi

    echo "$result"
}

# 下载升级包
download_upgrade() {
    local version="$1"
    local download_dir="$INSTALL_DIR/storage/upgrades"

    ensure_dir "$download_dir"

    log_info "下载升级包..."

    # 构建下载 URL
    local base_url=""
    if is_china_server; then
        base_url="https://gitee.com/cnssl/ssl-manager/releases/download"
    else
        base_url="https://github.com/cnssl/ssl-manager/releases/download"
    fi

    local download_url="$base_url/v$version/ssl-manager-upgrade-$version.zip"
    local save_path="$download_dir/ssl-manager-upgrade-$version.zip"

    if curl -L -o "$save_path" "$download_url"; then
        log_success "下载完成: $save_path"
        UPGRADE_FILE="$save_path"
    else
        log_error "下载失败"
        exit 1
    fi
}

# 创建备份
create_backup() {
    log_step "创建备份"

    local backup_dir="$INSTALL_DIR/storage/backups"
    local timestamp=$(get_timestamp)
    local backup_path="$backup_dir/$timestamp"

    ensure_dir "$backup_path"

    # 备份后端代码
    log_info "备份代码..."
    cp -r "$INSTALL_DIR/backend" "$backup_path/"

    # 备份数据库
    log_info "备份数据库..."
    if [ "$DEPLOY_MODE" = "docker" ]; then
        local compose_cmd=$(check_docker_compose)
        cd "$INSTALL_DIR"
        $compose_cmd exec -T php php artisan backup:run --only-db 2>/dev/null || true
    else
        cd "$INSTALL_DIR/backend"
        php artisan backup:run --only-db 2>/dev/null || true
    fi

    # 记录备份信息
    echo "{\"version\": \"$(get_current_version)\", \"timestamp\": \"$timestamp\"}" > "$backup_path/backup.json"

    log_success "备份完成: $backup_path"
    echo "$backup_path"
}

# 执行升级
perform_upgrade() {
    log_step "执行升级"

    if [ -z "$UPGRADE_FILE" ]; then
        log_error "未指定升级包"
        exit 1
    fi

    if [ ! -f "$UPGRADE_FILE" ]; then
        log_error "升级包不存在: $UPGRADE_FILE"
        exit 1
    fi

    # 创建备份
    local backup_path=$(create_backup)

    # 进入维护模式
    log_info "进入维护模式..."
    if [ "$DEPLOY_MODE" = "docker" ]; then
        local compose_cmd=$(check_docker_compose)
        cd "$INSTALL_DIR"
        $compose_cmd exec -T php php artisan down || true
    else
        cd "$INSTALL_DIR/backend"
        php artisan down || true
    fi

    # 解压升级包
    log_info "解压升级包..."
    local temp_dir="/tmp/ssl-manager-upgrade-$$"
    ensure_dir "$temp_dir"
    unzip -q "$UPGRADE_FILE" -d "$temp_dir"

    # 复制文件（升级包内容在 upgrade/ 子目录中）
    log_info "更新文件..."
    local src_dir="$temp_dir"
    [ -d "$temp_dir/upgrade" ] && src_dir="$temp_dir/upgrade"

    if [ -d "$src_dir/backend" ]; then
        # 保留 .env 和 storage
        rsync -a --exclude='.env' --exclude='storage/*' --exclude='vendor/*' \
            "$src_dir/backend/" "$INSTALL_DIR/backend/"
    fi

    # 更新前端
    if [ -d "$src_dir/frontend" ]; then
        # 新结构：前端在 frontend/ 子目录中
        for app in admin user easy; do
            if [ -d "$src_dir/frontend/$app" ]; then
                cp -rf "$src_dir/frontend/$app"/* "$INSTALL_DIR/$app/" 2>/dev/null || true
            fi
        done
    else
        # 兼容旧格式（前端直接在根目录）
        for app in admin user easy; do
            if [ -d "$src_dir/$app" ]; then
                cp -rf "$src_dir/$app"/* "$INSTALL_DIR/$app/" 2>/dev/null || true
            fi
        done
    fi

    # 安装 Composer 依赖
    log_info "安装 Composer 依赖..."
    if [ "$DEPLOY_MODE" = "docker" ]; then
        # Docker 环境：检测并配置镜像
        if is_china_server; then
            log_info "配置 Composer 中国镜像..."
            $compose_cmd exec -T php composer config repo.packagist composer https://mirrors.aliyun.com/composer/
        fi
        $compose_cmd exec -T php composer install --no-dev --optimize-autoloader
    else
        cd "$INSTALL_DIR/backend"
        # 宝塔环境：检测并配置镜像
        if is_china_server; then
            log_info "配置 Composer 中国镜像..."
            composer config repo.packagist composer https://mirrors.aliyun.com/composer/
        fi
        COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader
    fi

    # 运行迁移
    log_info "运行数据库迁移..."
    if [ "$DEPLOY_MODE" = "docker" ]; then
        $compose_cmd exec -T php php artisan migrate --force
    else
        cd "$INSTALL_DIR/backend"
        php artisan migrate --force
    fi

    # 清理缓存
    log_info "清理缓存..."
    if [ "$DEPLOY_MODE" = "docker" ]; then
        $compose_cmd exec -T php php artisan config:cache
        $compose_cmd exec -T php php artisan route:cache
        $compose_cmd exec -T php php artisan view:cache
    else
        php artisan config:cache
        php artisan route:cache
        php artisan view:cache
    fi

    # 退出维护模式
    log_info "退出维护模式..."
    if [ "$DEPLOY_MODE" = "docker" ]; then
        $compose_cmd exec -T php php artisan up
    else
        php artisan up
    fi

    # 清理临时文件
    rm -rf "$temp_dir"

    log_success "升级完成"
}

# 回滚
rollback() {
    log_step "执行回滚"

    local backup_dir="$INSTALL_DIR/storage/backups"

    # 查找最新备份
    local latest_backup=$(ls -td "$backup_dir"/*/ 2>/dev/null | head -1)

    if [ -z "$latest_backup" ]; then
        log_error "未找到可用备份"
        exit 1
    fi

    log_info "使用备份: $latest_backup"

    if ! confirm "确认回滚到此备份？"; then
        exit 0
    fi

    # 进入维护模式
    log_info "进入维护模式..."
    if [ "$DEPLOY_MODE" = "docker" ]; then
        local compose_cmd=$(check_docker_compose)
        cd "$INSTALL_DIR"
        $compose_cmd exec -T php php artisan down || true
    else
        cd "$INSTALL_DIR/backend"
        php artisan down || true
    fi

    # 恢复文件
    log_info "恢复文件..."
    rm -rf "$INSTALL_DIR/backend"
    cp -r "$latest_backup/backend" "$INSTALL_DIR/"

    # 退出维护模式
    log_info "退出维护模式..."
    if [ "$DEPLOY_MODE" = "docker" ]; then
        $compose_cmd exec -T php php artisan up
    else
        cd "$INSTALL_DIR/backend"
        php artisan up
    fi

    log_success "回滚完成"
}

# 主函数
main() {
    parse_args "$@"

    # 检测安装目录
    detect_install_dir
    log_info "安装目录: $INSTALL_DIR"

    case "${ACTION:-upgrade}" in
        check)
            check_update
            ;;
        rollback)
            rollback
            ;;
        upgrade|*)
            if [ -z "$UPGRADE_FILE" ] && [ -z "$TARGET_VERSION" ]; then
                # 检查更新并获取最新版本
                log_info "检查可用更新..."
                # 这里应该调用 API 获取最新版本
                # 暂时跳过，等后端 API 实现
                log_warning "在线升级功能尚未完成，请使用 --file 参数指定升级包"
                exit 1
            fi

            if [ -n "$TARGET_VERSION" ] && [ -z "$UPGRADE_FILE" ]; then
                download_upgrade "$TARGET_VERSION"
            fi

            perform_upgrade
            ;;
    esac
}

# 运行主函数
main "$@"
