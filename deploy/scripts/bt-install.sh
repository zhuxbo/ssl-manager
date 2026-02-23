#!/bin/bash

# SSL证书管理系统 - 宝塔环境安装脚本

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEPLOY_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

# 加载公共函数
source "$SCRIPT_DIR/common.sh"

# 全局变量
INSTALL_DIR="${INSTALL_DIR:-}"  # 支持通过环境变量预设
PHP_VERSION=""
PHP_CMD=""
AUTO_YES="${AUTO_YES:-false}"   # 非交互模式

# 解析参数
while [[ $# -gt 0 ]]; do
    case "$1" in
        -y)
            AUTO_YES=true
            shift
            ;;
        *)
            shift
            ;;
    esac
done

# 检测宝塔环境
check_environment() {
    log_step "检测宝塔环境"

    if ! check_bt_panel; then
        log_error "未检测到宝塔面板环境"
        log_info "脚本部署仅支持宝塔面板环境"
        log_info "请选择以下方式之一："
        log_info "  1. 安装宝塔面板后重试: https://www.bt.cn/new/download.html"
        log_info "  2. 使用 Docker 部署: ./install.sh docker"
        exit 1
    fi

    log_success "检测到宝塔面板环境"
}

# 选择 PHP 版本（仅支持 8.3/8.4）
select_php_version() {
    log_step "检测 PHP 版本"

    local php_versions=()

    for ver in 84 83; do
        if [ -d "/www/server/php/$ver" ] && [ -x "/www/server/php/$ver/bin/php" ]; then
            php_versions+=("$ver")
        fi
    done

    if [ ${#php_versions[@]} -eq 0 ]; then
        log_error "未检测到 PHP 8.3 或 8.4"
        log_info "请在宝塔面板中安装 PHP 8.3 或 8.4"
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
        if ! bash "$SCRIPT_DIR/bt-deps.sh"; then
            log_error "依赖检测未通过，请按提示处理后重试"
            exit 1
        fi
    fi

    log_success "依赖检测完成"
}

# 选择安装目录
select_install_dir() {
    log_step "选择安装目录"

    # 如果已通过环境变量设置了 INSTALL_DIR，直接使用
    if [ -n "$INSTALL_DIR" ]; then
        log_info "使用预设安装目录: $INSTALL_DIR"
    else
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
    fi

    # 检查目录
    if [ -d "$INSTALL_DIR" ] && [ "$(ls -A "$INSTALL_DIR" 2>/dev/null | grep -v '^\.' | head -1)" ]; then
        log_warning "目录已存在: $INSTALL_DIR"
        if [ -z "$AUTO_YES" ] || [ "$AUTO_YES" != "true" ]; then
            if ! confirm "是否覆盖安装？"; then
                exit 0
            fi
        fi
    fi

    # 创建目录（如果不存在）
    if [ ! -d "$INSTALL_DIR" ]; then
        mkdir -p "$INSTALL_DIR"
        chown www:www "$INSTALL_DIR"
    fi
    log_success "安装目录: $INSTALL_DIR"
}

# 下载应用代码
download_application() {
    log_step "下载应用程序"

    # 使用环境变量中的版本，默认为 latest
    local version="${INSTALL_VERSION:-latest}"

    local temp_dir="/tmp/ssl-manager-download-$$"
    mkdir -p "$temp_dir"

    # 下载完整包到临时目录
    local zip_file="$temp_dir/full.zip"
    local filename
    case "$version" in
        latest|dev|dev-latest)
            filename="ssl-manager-full-latest.zip"
            ;;
        *)
            filename="ssl-manager-full-${version}.zip"
            ;;
    esac

    if ! download_release_file "$filename" "$zip_file" "$version"; then
        log_error "下载失败"
        rm -rf "$temp_dir"
        exit 1
    fi

    # 解压文件
    log_info "解压文件..."
    unzip -qo "$zip_file" -d "$temp_dir"

    # 找到解压后的目录
    local extract_dir="$temp_dir/ssl-manager"
    if [ ! -d "$extract_dir" ]; then
        extract_dir=$(find "$temp_dir" -mindepth 1 -maxdepth 1 -type d -name "ssl-manager*" | head -1)
    fi
    if [ ! -d "$extract_dir" ]; then
        extract_dir="$temp_dir/full"
    fi

    if [ ! -d "$extract_dir" ]; then
        log_error "未找到解压后的目录"
        rm -rf "$temp_dir"
        exit 1
    fi

    # 复制文件到安装目录
    if [ -d "$extract_dir/backend" ]; then
        log_info "复制后端代码..."
        cp -r "$extract_dir/backend" "$INSTALL_DIR/"
    else
        log_error "未找到后端代码"
        rm -rf "$temp_dir"
        exit 1
    fi

    if [ -d "$extract_dir/frontend" ]; then
        log_info "复制前端代码..."
        cp -r "$extract_dir/frontend" "$INSTALL_DIR/"
    fi

    # 复制 Nginx 配置
    if [ -d "$extract_dir/nginx" ]; then
        mkdir -p "$INSTALL_DIR/nginx"
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

    # 替换 frontend/web 配置中的占位符
    if [ -d "$INSTALL_DIR/frontend/web" ]; then
        for conf_file in "$INSTALL_DIR/frontend/web"/*.conf; do
            if [ -f "$conf_file" ]; then
                sed -i "s|__PROJECT_ROOT__|$INSTALL_DIR|g" "$conf_file"
            fi
        done
    fi

    # 复制版本配置并注入 release_url 和 network
    if [ -f "$extract_dir/version.json" ]; then
        cp "$extract_dir/version.json" "$INSTALL_DIR/"
        local version_file="$INSTALL_DIR/version.json"

        # 使用 PHP 处理 JSON（确保格式正确）
        $PHP_CMD -r "
            \$json = json_decode(file_get_contents('$version_file'), true);
            // 注入 release_url
            if (!empty('$CUSTOM_RELEASE_URL')) {
                \$json['release_url'] = '$CUSTOM_RELEASE_URL';
            }
            // 注入 network 配置（从环境变量读取）
            \$network = getenv('NETWORK_ENV') ?: 'china';
            \$json['network'] = \$network;
            file_put_contents('$version_file', json_encode(\$json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . \"\\n\");
        "

        if [ -n "$CUSTOM_RELEASE_URL" ]; then
            log_info "已配置 release_url: $CUSTOM_RELEASE_URL"
        fi
        if [ -n "$NETWORK_ENV" ]; then
            log_info "已配置 network: $NETWORK_ENV"
        fi
    fi

    # 清理临时文件
    rm -rf "$temp_dir"

    log_success "下载完成"
}

# 检测 Composer（仅检测，不安装依赖，依赖安装在 Web 安装向导中执行）
check_composer() {
    log_step "检测 Composer"

    # 检查 Composer
    if command -v composer &> /dev/null; then
        log_success "Composer 已安装: $(which composer)"
        return 0
    elif [ -f "/usr/local/bin/composer" ]; then
        log_success "Composer 已安装: /usr/local/bin/composer"
        return 0
    fi

    # 安装 Composer（在临时目录中执行，避免污染当前目录）
    log_info "安装 Composer..."
    local temp_composer_dir="/tmp/composer-install-$$"
    mkdir -p "$temp_composer_dir"
    cd "$temp_composer_dir"
    curl -sS https://getcomposer.org/installer | $PHP_CMD
    mv composer.phar /usr/local/bin/composer
    chmod +x /usr/local/bin/composer
    cd - > /dev/null
    rm -rf "$temp_composer_dir"
    log_success "Composer 安装完成"
}

# 设置权限
set_permissions() {
    log_step "设置文件权限"

    # 创建 backups 目录（备份和升级包存储）
    mkdir -p "$INSTALL_DIR/backups/upgrades"

    # 使用 chown -R 一次性设置权限（比 find -exec 快得多）
    # 忽略 .user.ini 的错误（宝塔会锁定此文件）
    chown -R www:www "$INSTALL_DIR" 2>/dev/null || true

    # 确保 storage 和 cache 目录可写
    if [ -d "$INSTALL_DIR/backend/storage" ]; then
        chmod -R 775 "$INSTALL_DIR/backend/storage" 2>/dev/null || true
    fi

    if [ -d "$INSTALL_DIR/backend/bootstrap/cache" ]; then
        chmod -R 775 "$INSTALL_DIR/backend/bootstrap/cache" 2>/dev/null || true
    fi

    # 确保 backups 目录可写
    chmod -R 775 "$INSTALL_DIR/backups" 2>/dev/null || true

    # 验证权限设置
    local owner=$(stat -c '%U' "$INSTALL_DIR/backend" 2>/dev/null || echo "unknown")
    if [ "$owner" = "www" ]; then
        log_success "权限设置完成"
    else
        log_warning "权限设置可能未完全生效，当前所有者: $owner"
        log_info "请手动执行: chown -R www:www $INSTALL_DIR"
    fi
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
    echo "   - 网站目录: $INSTALL_DIR"
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
    echo "       环境准备完成"
    echo "============================================"
    echo
    echo "安装目录: $INSTALL_DIR"
    echo "PHP 版本: 8.${PHP_VERSION: -1}"
    echo
    echo "下一步操作:"
    echo "  1. 在宝塔面板中配置 Nginx（见上方提示）"
    echo "  2. 访问 http://您的域名/install.php 完成安装向导"
    echo "     - 安装向导将自动安装 Composer 依赖"
    echo "     - 根据网络环境自动选择国内或国际镜像源"
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

    # 检测 Composer（仅检测，依赖安装在 Web 向导中执行）
    check_composer

    # 设置权限
    set_permissions

    # 显示 Nginx 配置提示
    show_nginx_tips

    # 显示完成信息
    show_complete_info
}

# 运行主函数
main "$@"
