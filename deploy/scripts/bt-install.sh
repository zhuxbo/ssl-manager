#!/bin/bash

# SSL证书管理系统 - 宝塔环境安装脚本

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEPLOY_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

# 加载公共函数
source "$SCRIPT_DIR/common.sh"

# 全局变量
FORCE_REPO="${1:-}"
INSTALL_DIR=""
PHP_VERSION=""
PHP_CMD=""

# 检测宝塔环境
check_environment() {
    log_step "检测宝塔环境"

    if ! check_bt_panel; then
        log_error "未检测到宝塔面板环境"
        log_info "脚本部署仅支持宝塔面板环境"
        log_info "请选择以下方式之一："
        log_info "  1. 安装宝塔面板后重试: https://www.bt.cn/new/download.html"
        log_info "  2. 使用 Docker 部署: ./deploy.sh docker"
        exit 1
    fi

    log_success "检测到宝塔面板环境"
}

# 选择 PHP 版本
select_php_version() {
    log_step "检测 PHP 版本"

    local php_versions=()

    for ver in 85 84 83; do
        if [ -d "/www/server/php/$ver" ] && [ -x "/www/server/php/$ver/bin/php" ]; then
            php_versions+=("$ver")
        fi
    done

    if [ ${#php_versions[@]} -eq 0 ]; then
        log_error "未检测到 PHP 8.3 或更高版本"
        log_info "请在宝塔面板中安装 PHP 8.3+"
        exit 1
    elif [ ${#php_versions[@]} -eq 1 ]; then
        PHP_VERSION="${php_versions[0]}"
    else
        log_info "检测到多个可用的 PHP 版本："
        for i in "${!php_versions[@]}"; do
            local ver="${php_versions[$i]}"
            echo "  $((i+1)). PHP 8.${ver: -1}"
        done

        while true; do
            read -p "请选择 (1-${#php_versions[@]}): " choice < /dev/tty
            if [[ "$choice" =~ ^[0-9]+$ ]] && [ "$choice" -ge 1 ] && [ "$choice" -le ${#php_versions[@]} ]; then
                PHP_VERSION="${php_versions[$((choice-1))]}"
                break
            fi
            log_error "无效选择"
        done
    fi

    PHP_CMD="/www/server/php/$PHP_VERSION/bin/php"
    log_success "使用 PHP 8.${PHP_VERSION: -1}"
}

# 检测依赖
check_dependencies() {
    log_step "检测系统依赖"

    # 运行依赖检测脚本
    if [ -f "$SCRIPT_DIR/bt-deps.sh" ]; then
        bash "$SCRIPT_DIR/bt-deps.sh"
    fi

    log_success "依赖检测完成"
}

# 选择安装目录
select_install_dir() {
    log_step "选择安装目录"

    echo "请选择安装方式："
    echo "  1. 安装到宝塔网站目录（推荐，方便管理）"
    echo "  2. 自定义安装目录"
    echo

    read -p "请选择 (1/2): " choice < /dev/tty

    case "$choice" in
        1)
            echo
            read -p "请输入网站域名或目录名: " site_name < /dev/tty
            INSTALL_DIR="/www/wwwroot/$site_name"
            ;;
        2)
            read -p "请输入安装目录: " INSTALL_DIR < /dev/tty
            ;;
        *)
            INSTALL_DIR="/www/wwwroot/ssl-manager"
            ;;
    esac

    # 检查目录
    if [ -d "$INSTALL_DIR" ]; then
        log_warning "目录已存在: $INSTALL_DIR"
        if ! confirm "是否覆盖安装？"; then
            exit 0
        fi
    fi

    ensure_dir "$INSTALL_DIR"
    log_success "安装目录: $INSTALL_DIR"
}

# 下载应用代码
download_application() {
    log_step "下载应用程序"

    # 使用环境变量中的版本，默认为 latest
    local version="${INSTALL_VERSION:-latest}"

    local temp_dir="/tmp/ssl-manager-download-$$"
    mkdir -p "$temp_dir"

    # 下载完整包
    if ! download_and_extract_full "$temp_dir" "$version"; then
        log_error "下载失败"
        rm -rf "$temp_dir"
        exit 1
    fi

    # 找到解压后的目录
    local extract_dir="$temp_dir/ssl-manager"
    if [ ! -d "$extract_dir" ]; then
        extract_dir=$(find "$temp_dir" -maxdepth 1 -type d -name "ssl-manager*" | head -1)
    fi

    # 有时候完整包直接解压在根目录（full 目录）
    if [ ! -d "$extract_dir" ]; then
        extract_dir="$temp_dir/full"
    fi

    if [ ! -d "$extract_dir" ]; then
        log_error "未找到解压后的目录"
        rm -rf "$temp_dir"
        exit 1
    fi

    # 复制后端代码
    if [ -d "$extract_dir/backend" ]; then
        log_info "复制后端代码..."
        cp -r "$extract_dir/backend" "$INSTALL_DIR/"
    else
        log_error "未找到后端代码"
        rm -rf "$temp_dir"
        exit 1
    fi

    # 复制前端代码
    if [ -d "$extract_dir/frontend" ]; then
        log_info "复制前端代码..."
        cp -r "$extract_dir/frontend" "$INSTALL_DIR/"
    fi

    # 复制 Nginx 配置
    if [ -d "$extract_dir/nginx" ]; then
        ensure_dir "$INSTALL_DIR/nginx"
        cp "$extract_dir/nginx"/*.conf "$INSTALL_DIR/nginx/" 2>/dev/null || true

        # 替换 nginx 配置中的占位符
        log_info "处理 nginx 配置..."
        for conf_file in "$INSTALL_DIR/nginx"/*.conf; do
            if [ -f "$conf_file" ]; then
                sed -i "s|__PROJECT_ROOT__|$INSTALL_DIR|g" "$conf_file"
            fi
        done
        log_success "nginx 配置已更新"
    fi

    # 复制版本配置
    if [ -f "$extract_dir/config.json" ]; then
        cp "$extract_dir/config.json" "$INSTALL_DIR/"
    fi

    # 清理临时文件
    rm -rf "$temp_dir"

    log_success "下载完成"
}

# 安装 Composer 依赖
install_composer_deps() {
    log_step "安装 Composer 依赖"

    cd "$INSTALL_DIR/backend"

    # 检查 Composer
    if ! command -v composer &> /dev/null; then
        log_info "安装 Composer..."
        curl -sS https://getcomposer.org/installer | $PHP_CMD
        sudo mv composer.phar /usr/local/bin/composer
        sudo chmod +x /usr/local/bin/composer
    fi

    # 配置中国镜像
    if is_china_server; then
        log_info "配置 Composer 中国镜像..."
        # 使用腾讯云镜像（对 GitHub 包有更好的代理）
        composer config -g repo.packagist composer https://mirrors.tencent.com/composer/
        # 配置 GitHub 使用 OAuth 或增加超时（避免 GitHub 下载超时）
        composer config -g process-timeout 600
        composer config -g github-protocols https
    fi

    # 安装依赖
    log_info "安装依赖包..."
    COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader

    log_success "Composer 依赖安装完成"
}

# 配置环境
configure_environment() {
    log_step "配置环境"

    cd "$INSTALL_DIR/backend"

    # 复制环境配置
    if [ ! -f ".env" ]; then
        if [ -f ".env.example" ]; then
            cp .env.example .env
        else
            log_error "未找到 .env.example 文件"
            exit 1
        fi
    fi

    # 生成应用密钥
    $PHP_CMD artisan key:generate --force

    log_success "环境配置完成"
}

# 设置权限
set_permissions() {
    log_step "设置文件权限"

    cd "$INSTALL_DIR"

    # 设置所有者（排除 .user.ini，宝塔会锁定此文件）
    find "$INSTALL_DIR" -not -name ".user.ini" -exec chown www:www {} \; 2>/dev/null || true

    # 设置目录权限
    find "$INSTALL_DIR" -type d -exec chmod 755 {} \; 2>/dev/null || true

    # 设置文件权限（排除 .user.ini）
    find "$INSTALL_DIR" -type f -not -name ".user.ini" -exec chmod 644 {} \; 2>/dev/null || true

    # 设置 storage 和 cache 目录权限
    chmod -R 775 "$INSTALL_DIR/backend/storage" 2>/dev/null || true
    chmod -R 775 "$INSTALL_DIR/backend/bootstrap/cache" 2>/dev/null || true

    log_success "权限设置完成"
}

# 显示 Nginx 配置提示
show_nginx_tips() {
    echo
    log_step "Nginx 配置"
    echo
    echo "请在宝塔面板中进行以下配置："
    echo
    echo "1. 创建网站（如果尚未创建）"
    echo "   - 域名: 您的域名"
    echo "   - 网站目录: $INSTALL_DIR/backend"
    echo "   - 运行目录: /public"
    echo "   - PHP版本: 8.${PHP_VERSION: -1}"
    echo
    echo "2. 在网站配置中添加以下内容（配置文件 → 自定义配置）:"
    echo "   include $INSTALL_DIR/nginx/manager.conf;"
    echo
}

# 显示完成信息
show_complete_info() {
    echo
    echo "============================================"
    echo "       安装完成"
    echo "============================================"
    echo
    echo "安装目录: $INSTALL_DIR"
    echo "PHP 版本: 8.${PHP_VERSION: -1}"
    echo
    echo "下一步操作:"
    echo "  1. 在宝塔面板中配置 Nginx（见上方提示）"
    echo "  2. 访问 http://您的域名/install 完成安装向导"
    echo "  3. 配置队列和定时任务"
    echo
    echo "队列配置（宝塔 → 计划任务 → 添加守护进程）:"
    echo "  名称: ssl-manager-queue"
    echo "  命令: $PHP_CMD $INSTALL_DIR/backend/artisan queue:work --queue tasks,notifications --sleep=3 --tries=3 --max-time 3600"
    echo
    echo "定时任务（宝塔 → 计划任务 → 添加任务）:"
    echo "  执行周期: 每分钟"
    echo "  命令: $PHP_CMD $INSTALL_DIR/backend/artisan schedule:run"
    echo
}

# 主函数
main() {
    log_step "开始宝塔环境安装"

    # 检测环境
    check_environment

    # 选择 PHP 版本
    select_php_version

    # 检测依赖
    check_dependencies

    # 选择安装目录
    select_install_dir

    # 下载应用代码
    download_application

    # 安装 Composer 依赖
    install_composer_deps

    # 配置环境
    configure_environment

    # 设置权限
    set_permissions

    # 显示 Nginx 配置提示
    show_nginx_tips

    # 显示完成信息
    show_complete_info
}

# 运行主函数
main "$@"
