#!/bin/bash

# SSL证书管理系统 - 统一部署入口脚本
# 支持宝塔脚本部署和 Docker 部署两种模式

set -e

# 获取脚本所在目录
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# 加载公共函数
source "$SCRIPT_DIR/scripts/common.sh"

# 全局变量
DEPLOY_MODE=""
FORCE_REPO=""
ACTION="install"
PACKAGE_FILE=""
AUTO_YES=false

# 显示帮助信息
show_help() {
    cat <<EOF
SSL证书管理系统 - 统一部署脚本

用法: $0 [模式] [选项] [动作]

模式:
  bt, baota         宝塔面板脚本部署（默认自动检测）
  docker            Docker 容器化部署

动作:
  install           安装系统（默认）
  update            更新系统
  upgrade           在线升级
  uninstall         卸载系统

选项:
  --file PATH       从本地安装包部署
  -y, --yes         自动确认，非交互模式
  gitee             强制从 Gitee 拉取
  github            强制从 GitHub 拉取
  -h, help          显示帮助信息

示例:
  $0                      # 自动检测环境并安装
  $0 docker               # Docker 部署
  $0 bt                   # 宝塔脚本部署
  $0 docker update        # Docker 模式更新
  $0 upgrade              # 在线升级
  $0 --file /path/to/package.zip docker -y  # 从本地包自动部署

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
            --file)
                PACKAGE_FILE="$2"
                shift 2
                ;;
            -y|--yes)
                AUTO_YES=true
                shift
                ;;
            gitee)
                FORCE_REPO="gitee"
                shift
                ;;
            github)
                FORCE_REPO="github"
                shift
                ;;
            bt|baota)
                DEPLOY_MODE="bt"
                shift
                ;;
            docker)
                DEPLOY_MODE="docker"
                shift
                ;;
            install|update|upgrade|uninstall)
                ACTION="$1"
                shift
                ;;
            *)
                log_error "未知参数: $1"
                show_help
                ;;
        esac
    done
}

# 自动检测部署模式
detect_deploy_mode() {
    if [ -n "$DEPLOY_MODE" ]; then
        return
    fi

    log_info "自动检测部署环境..."

    # 检测宝塔环境
    if check_bt_panel; then
        log_info "检测到宝塔面板环境"
        DEPLOY_MODE="bt"
        return
    fi

    # 检测 Docker 环境
    if check_docker; then
        log_info "检测到 Docker 环境"
        DEPLOY_MODE="docker"
        return
    fi

    # 无法自动检测，显示选择菜单
    echo
    log_warning "未检测到宝塔面板或 Docker 环境"
    echo
    echo "请选择部署方式："
    echo "  1. Docker 部署（推荐，自动安装依赖）"
    echo "  2. 宝塔脚本部署（需要先安装宝塔面板）"
    echo "  3. 退出"
    echo

    read -p "请选择 (1-3): " choice < /dev/tty
    case "$choice" in
        1)
            DEPLOY_MODE="docker"
            ;;
        2)
            log_error "脚本部署仅支持宝塔面板环境"
            log_info "请先安装宝塔面板: https://www.bt.cn/new/download.html"
            log_info "或选择 Docker 部署方式"
            exit 1
            ;;
        *)
            log_info "退出部署"
            exit 0
            ;;
    esac
}

# 宝塔部署入口
deploy_bt() {
    local action="$1"

    # 再次确认宝塔环境
    if ! check_bt_panel; then
        log_error "未检测到宝塔面板环境"
        log_info "脚本部署仅支持宝塔面板环境"
        log_info "请选择以下方式之一："
        log_info "  1. 安装宝塔面板后重试: https://www.bt.cn/new/download.html"
        log_info "  2. 使用 Docker 部署: $0 docker"
        exit 1
    fi

    case "$action" in
        install)
            log_step "开始宝塔环境安装..."
            bash "$SCRIPT_DIR/scripts/bt-install.sh" $FORCE_REPO
            ;;
        update)
            log_step "开始宝塔环境更新..."
            bash "$SCRIPT_DIR/scripts/bt-update.sh" $FORCE_REPO
            ;;
        upgrade)
            log_step "开始在线升级..."
            bash "$SCRIPT_DIR/scripts/upgrade.sh"
            ;;
        uninstall)
            log_warning "卸载功能暂未实现"
            ;;
    esac
}

# Docker 部署入口
deploy_docker() {
    local action="$1"

    # 检查 Docker
    local docker_status
    docker_status=$(check_docker; echo $?)

    if [ "$docker_status" -eq 1 ]; then
        log_error "未安装 Docker"
        log_info "请先安装 Docker: https://docs.docker.com/get-docker/"

        if confirm "是否自动安装 Docker？"; then
            install_docker
        else
            exit 1
        fi
    elif [ "$docker_status" -eq 2 ]; then
        log_error "Docker 服务未运行"
        log_info "请启动 Docker 服务: sudo systemctl start docker"
        exit 1
    fi

    # 构建参数
    local args=""
    [ -n "$FORCE_REPO" ] && args="$args $FORCE_REPO"
    [ -n "$PACKAGE_FILE" ] && args="$args --file $PACKAGE_FILE"
    [ "$AUTO_YES" = true ] && args="$args -y"

    case "$action" in
        install)
            log_step "开始 Docker 部署..."
            bash "$SCRIPT_DIR/scripts/docker-install.sh" $args
            ;;
        update)
            log_step "开始 Docker 更新..."
            bash "$SCRIPT_DIR/scripts/docker-update.sh" $args
            ;;
        upgrade)
            log_step "开始在线升级..."
            bash "$SCRIPT_DIR/scripts/upgrade.sh" docker
            ;;
        uninstall)
            log_step "开始卸载..."
            bash "$SCRIPT_DIR/scripts/docker-uninstall.sh"
            ;;
    esac
}

# 自动安装 Docker
install_docker() {
    log_info "开始安装 Docker..."

    if is_china_server; then
        log_info "使用中国镜像源安装..."
        curl -fsSL https://get.docker.com | bash -s docker --mirror Aliyun
    else
        curl -fsSL https://get.docker.com | bash
    fi

    # 启动 Docker 服务
    sudo systemctl enable docker
    sudo systemctl start docker

    # 将当前用户添加到 docker 组
    if [ "$EUID" -ne 0 ]; then
        sudo usermod -aG docker "$USER"
        log_warning "已将当前用户添加到 docker 组，请重新登录后再运行此脚本"
        exit 0
    fi

    log_success "Docker 安装完成"
}

# 显示横幅
show_banner() {
    echo
    echo "============================================"
    echo "       SSL证书管理系统 - 部署工具"
    echo "============================================"
    echo
}

# 主函数
main() {
    show_banner

    # 解析参数
    parse_args "$@"

    # 自动检测部署模式
    detect_deploy_mode

    log_info "部署模式: $DEPLOY_MODE"
    log_info "执行动作: $ACTION"
    if [ -n "$FORCE_REPO" ]; then
        log_info "仓库源: $FORCE_REPO"
    fi
    echo

    # 执行部署
    case "$DEPLOY_MODE" in
        bt)
            deploy_bt "$ACTION"
            ;;
        docker)
            deploy_docker "$ACTION"
            ;;
        *)
            log_error "未知部署模式: $DEPLOY_MODE"
            exit 1
            ;;
    esac
}

# 运行主函数
main "$@"
