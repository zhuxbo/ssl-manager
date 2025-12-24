#!/bin/bash

# SSL证书管理系统 - 宝塔环境依赖检测脚本

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/common.sh"

# 检测 PHP 版本和扩展
check_php() {
    log_step "检测 PHP 环境"

    local php_found=false

    for ver in 85 84 83; do
        if [ -d "/www/server/php/$ver" ] && [ -x "/www/server/php/$ver/bin/php" ]; then
            php_found=true
            local php_cmd="/www/server/php/$ver/bin/php"
            local php_version=$($php_cmd -v | head -1)
            log_success "PHP 8.${ver: -1}: $php_version"

            # 检查必要扩展
            local required_ext=("pdo_mysql" "redis" "gd" "zip" "bcmath" "pcntl" "intl")
            local missing_ext=()

            for ext in "${required_ext[@]}"; do
                if ! $php_cmd -m 2>/dev/null | grep -qi "^$ext$"; then
                    missing_ext+=("$ext")
                fi
            done

            if [ ${#missing_ext[@]} -gt 0 ]; then
                log_warning "缺少扩展: ${missing_ext[*]}"
                log_info "请在宝塔面板中安装以上扩展"
            else
                log_success "所有必要扩展已安装"
            fi
        fi
    done

    if [ "$php_found" = false ]; then
        log_error "未找到 PHP 8.3+"
        log_info "请在宝塔面板安装 PHP 8.3 或更高版本"
        return 1
    fi
}

# 检测 MySQL
check_mysql() {
    log_step "检测 MySQL"

    if [ -d "/www/server/mysql" ] || command -v mysql &> /dev/null; then
        log_success "MySQL 已安装"
    else
        log_warning "未检测到 MySQL"
        log_info "请在宝塔面板安装 MySQL"
    fi
}

# 检测 Redis
check_redis() {
    log_step "检测 Redis"

    if [ -d "/www/server/redis" ] || command -v redis-server &> /dev/null; then
        log_success "Redis 已安装"
    else
        log_warning "未检测到 Redis"
        log_info "请在宝塔面板安装 Redis"
    fi
}

# 检测 Composer
check_composer() {
    log_step "检测 Composer"

    if command -v composer &> /dev/null; then
        local version=$(composer --version 2>/dev/null | grep -o '[0-9]\+\.[0-9]\+\.[0-9]\+' | head -1)
        log_success "Composer $version 已安装"
    else
        log_warning "未安装 Composer"
        log_info "将在安装时自动安装 Composer"
    fi
}

# 主函数
main() {
    echo
    echo "============================================"
    echo "       宝塔环境依赖检测"
    echo "============================================"
    echo

    check_php
    echo
    check_mysql
    echo
    check_redis
    echo
    check_composer
    echo

    log_info "依赖检测完成"
}

main "$@"
