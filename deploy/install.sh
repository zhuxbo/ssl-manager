#!/bin/bash

# SSL Manager 一键安装入口脚本
# 用法:
#   curl -fsSL https://gitee.com/zhuxbo/cert-manager/raw/main/deploy/install.sh | bash
#   curl -fsSL https://gitee.com/zhuxbo/cert-manager/raw/main/deploy/install.sh | bash -s docker
#   curl -fsSL https://gitee.com/zhuxbo/cert-manager/raw/main/deploy/install.sh | bash -s bt

set -e

# ========================================
# 配置
# ========================================
REPO_OWNER="zhuxbo"
REPO_NAME="cert-manager"
GITEE_BASE_URL="https://gitee.com/$REPO_OWNER/$REPO_NAME"
GITHUB_BASE_URL="https://github.com/$REPO_OWNER/$REPO_NAME"
TEMP_DIR="/tmp/ssl-manager-install-$$"
SCRIPT_PACKAGE="ssl-manager-script-latest.zip"

# ========================================
# 颜色定义
# ========================================
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

# ========================================
# 日志函数
# ========================================
log_info() { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[OK]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }
log_warning() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_step() { echo -e "${CYAN}[STEP]${NC} $1"; }

# ========================================
# 清理函数
# ========================================
cleanup() {
    if [ -d "$TEMP_DIR" ]; then
        rm -rf "$TEMP_DIR"
    fi
}

trap cleanup EXIT

# ========================================
# 检测函数
# ========================================

# 检测是否在中国
is_china_server() {
    # 如果环境变量已设置，直接使用
    if [ -n "$FORCE_CHINA_MIRROR" ]; then
        [ "$FORCE_CHINA_MIRROR" = "1" ] && return 0 || return 1
    fi

    # 检查阿里云
    local aliyun_region=$(timeout 1 curl -s "http://100.100.100.200/latest/meta-data/region-id" 2>/dev/null || echo "")
    if [ -n "$aliyun_region" ] && [[ "$aliyun_region" =~ ^cn- ]]; then
        return 0
    fi

    # 检查腾讯云
    local tencent_region=$(timeout 1 curl -s "http://metadata.tencentyun.com/latest/meta-data/region" 2>/dev/null || echo "")
    if [ -n "$tencent_region" ]; then
        if [[ "$tencent_region" =~ ^(ap-beijing|ap-shanghai|ap-guangzhou|ap-chengdu|ap-chongqing|ap-nanjing) ]]; then
            return 0
        fi
        return 1
    fi

    # 检查华为云
    local huawei_az=$(timeout 1 curl -s "http://169.254.169.254/openstack/latest/meta_data.json" 2>/dev/null | grep -o '"availability_zone":"[^"]*"' | head -1 || echo "")
    if [ -n "$huawei_az" ] && [[ "$huawei_az" =~ cn- ]]; then
        return 0
    fi

    # Fallback: 检测 GitHub API 是否可达
    if ! timeout 3 curl -s --head "https://api.github.com" >/dev/null 2>&1; then
        return 0
    fi

    return 1
}

# 检测宝塔面板
check_bt_panel() {
    if [ -f "/www/server/panel/BT-Panel" ] || \
       [ -f "/www/server/panel/class/panelPlugin.py" ] || \
       ([ -d "/www/server/panel" ] && [ -f "/www/server/panel/data/port.pl" ]); then
        return 0
    fi
    return 1
}

# 检测 Docker
check_docker() {
    if ! command -v docker &> /dev/null; then
        return 1
    fi
    if ! docker info &> /dev/null; then
        return 2  # Docker 服务未运行
    fi
    return 0
}

# ========================================
# 下载函数
# ========================================
download_script_package() {
    local save_path="$1"

    local gitee_url="$GITEE_BASE_URL/releases/download/latest/$SCRIPT_PACKAGE"
    local github_url="$GITHUB_BASE_URL/releases/download/latest/$SCRIPT_PACKAGE"

    log_info "下载脚本包..."

    # 优先尝试 Gitee
    log_info "尝试从 Gitee 下载..."
    if curl -fsSL --connect-timeout 10 --max-time 120 -o "$save_path" "$gitee_url" 2>/dev/null; then
        log_success "Gitee 下载成功"
        return 0
    fi

    # 回退到 GitHub
    log_warning "Gitee 下载失败，尝试 GitHub..."
    if curl -fsSL --connect-timeout 10 --max-time 120 -o "$save_path" "$github_url" 2>/dev/null; then
        log_success "GitHub 下载成功"
        return 0
    fi

    log_error "下载失败"
    return 1
}

# ========================================
# 显示横幅
# ========================================
show_banner() {
    echo ""
    echo -e "${CYAN}╔═══════════════════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║${NC}           ${GREEN}SSL Manager 一键安装程序${NC}                        ${CYAN}║${NC}"
    echo -e "${CYAN}║${NC}           ${BLUE}https://github.com/$REPO_OWNER/$REPO_NAME${NC}         ${CYAN}║${NC}"
    echo -e "${CYAN}╚═══════════════════════════════════════════════════════════╝${NC}"
    echo ""
}

# ========================================
# 主流程
# ========================================
main() {
    local mode="${1:-auto}"

    show_banner

    # 检查 root 权限
    if [ "$EUID" -ne 0 ]; then
        log_error "请使用 root 权限运行此脚本"
        log_info "用法: sudo bash $0"
        exit 1
    fi

    # 检查必需命令
    for cmd in curl unzip; do
        if ! command -v $cmd &> /dev/null; then
            log_error "缺少必需命令: $cmd"
            log_info "请先安装: apt install $cmd 或 yum install $cmd"
            exit 1
        fi
    done

    # 检测网络环境（如果用户未手动指定）
    log_step "检测网络环境..."
    if [ -n "$FORCE_CHINA_MIRROR" ]; then
        if [ "$FORCE_CHINA_MIRROR" = "1" ]; then
            log_info "FORCE_CHINA_MIRROR=1，强制使用国内镜像源"
        else
            log_info "FORCE_CHINA_MIRROR=0，强制使用国际源"
        fi
    elif is_china_server; then
        log_info "检测到中国大陆网络环境，将使用国内镜像源"
        export FORCE_CHINA_MIRROR=1
    else
        log_info "使用国际源"
        export FORCE_CHINA_MIRROR=0
    fi

    # 创建临时目录
    mkdir -p "$TEMP_DIR"

    # 下载脚本包
    log_step "下载安装脚本..."
    local package_file="$TEMP_DIR/$SCRIPT_PACKAGE"
    if ! download_script_package "$package_file"; then
        log_error "无法下载安装脚本包"
        exit 1
    fi

    # 解压脚本包
    log_step "解压安装脚本..."
    if ! unzip -qo "$package_file" -d "$TEMP_DIR"; then
        log_error "解压失败"
        exit 1
    fi

    # 找到解压后的脚本目录
    local script_dir="$TEMP_DIR/script-deploy/scripts"
    if [ ! -d "$script_dir" ]; then
        script_dir="$TEMP_DIR/scripts"
    fi

    if [ ! -d "$script_dir" ]; then
        log_error "未找到脚本目录"
        exit 1
    fi

    # 设置脚本可执行权限
    chmod +x "$script_dir"/*.sh 2>/dev/null || true

    # 根据模式或环境选择安装方式
    case "$mode" in
        bt)
            # 强制使用宝塔安装
            if check_bt_panel; then
                log_info "使用宝塔面板安装..."
                bash "$script_dir/bt-install.sh"
            else
                log_error "未检测到宝塔面板环境"
                log_info "请先安装宝塔面板: https://www.bt.cn/new/download.html"
                exit 1
            fi
            ;;
        docker)
            # 强制使用 Docker 安装
            log_info "使用 Docker 安装..."
            bash "$script_dir/docker-install.sh"
            ;;
        auto)
            # 自动检测环境
            log_step "检测运行环境..."

            if check_bt_panel; then
                log_success "检测到宝塔面板环境"
                echo ""
                echo "请选择安装方式:"
                echo "  1. 宝塔面板安装（推荐，适合已有宝塔环境）"
                echo "  2. Docker 安装（独立容器化部署）"
                echo ""
                read -p "请选择 (1/2) [1]: " choice < /dev/tty
                choice="${choice:-1}"

                case "$choice" in
                    2)
                        log_info "使用 Docker 安装..."
                        bash "$script_dir/docker-install.sh"
                        ;;
                    *)
                        log_info "使用宝塔面板安装..."
                        bash "$script_dir/bt-install.sh"
                        ;;
                esac
            elif check_docker; then
                log_success "检测到 Docker 环境"
                log_info "使用 Docker 安装..."
                bash "$script_dir/docker-install.sh"
            else
                log_warning "未检测到宝塔面板或 Docker 环境"
                echo ""
                echo "可选安装方式:"
                echo ""
                echo "  1. Docker 安装（推荐）"
                echo "     脚本将自动安装 Docker 并进行容器化部署"
                echo ""
                echo "  2. 宝塔面板安装"
                echo "     请先安装宝塔面板: https://www.bt.cn/new/download.html"
                echo "     然后重新运行此脚本"
                echo ""
                read -p "是否使用 Docker 安装？(y/n) [y]: " use_docker < /dev/tty
                use_docker="${use_docker:-y}"

                case "$use_docker" in
                    n|N)
                        log_info "请安装宝塔面板后重新运行此脚本"
                        exit 0
                        ;;
                    *)
                        log_info "使用 Docker 安装..."
                        bash "$script_dir/docker-install.sh"
                        ;;
                esac
            fi
            ;;
        *)
            log_error "未知的安装模式: $mode"
            echo ""
            echo "用法:"
            echo "  $0         # 自动检测环境"
            echo "  $0 docker  # 使用 Docker 安装"
            echo "  $0 bt      # 使用宝塔面板安装"
            exit 1
            ;;
    esac
}

# 运行主流程
main "$@"
