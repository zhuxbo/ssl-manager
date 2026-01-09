#!/bin/bash

# SSL Manager 一键安装入口脚本
# 用法:
#   curl -fsSL https://gitee.com/zhuxbo/cert-manager/raw/main/deploy/install.sh | bash
#   curl -fsSL https://gitee.com/zhuxbo/cert-manager/raw/main/deploy/install.sh | bash -s docker
#   curl -fsSL https://gitee.com/zhuxbo/cert-manager/raw/main/deploy/install.sh | bash -s bt
#   curl -fsSL https://gitee.com/zhuxbo/cert-manager/raw/main/deploy/install.sh | bash -s -- --version dev

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

# 检测服务器是否在中国大陆
# 多层检测，确保准确性
is_china_server() {
    # 如果环境变量已设置，直接使用
    if [ -n "$FORCE_CHINA_MIRROR" ]; then
        [ "$FORCE_CHINA_MIRROR" = "1" ] && return 0 || return 1
    fi

    # 1. 检查云服务商元数据 - 阿里云
    local aliyun_region=$(timeout 1 curl -s "http://100.100.100.200/latest/meta-data/region-id" 2>/dev/null || echo "")
    if [ -n "$aliyun_region" ] && [[ "$aliyun_region" =~ ^cn- ]]; then
        return 0
    fi

    # 检查云服务商元数据 - 腾讯云
    local tencent_region=$(timeout 1 curl -s "http://metadata.tencentyun.com/latest/meta-data/region" 2>/dev/null || echo "")
    if [ -n "$tencent_region" ]; then
        if [[ "$tencent_region" =~ ^(ap-beijing|ap-shanghai|ap-guangzhou|ap-chengdu|ap-chongqing|ap-nanjing) ]]; then
            return 0
        fi
        return 1
    fi

    # 检查云服务商元数据 - 华为云
    local huawei_az=$(timeout 1 curl -s "http://169.254.169.254/openstack/latest/meta_data.json" 2>/dev/null | grep -o '"availability_zone":"[^"]*"' | head -1 || echo "")
    if [ -n "$huawei_az" ] && [[ "$huawei_az" =~ cn- ]]; then
        return 0
    fi

    # 2. 检测 baidu.com 可达性 + Google 不可达
    local baidu_ok=false
    if timeout 2 curl -s --head "https://www.baidu.com" >/dev/null 2>&1; then
        baidu_ok=true
    fi

    if [ "$baidu_ok" = true ]; then
        # Google 在国内通常不可访问，如果不可达则认为是国内网络
        if ! timeout 3 curl -s --head "https://www.google.com" >/dev/null 2>&1; then
            return 0
        fi
    fi

    # 3. IP 归属地检测（备选方案）
    local country=""
    # 尝试 ip.sb
    country=$(timeout 3 curl -s "https://api.ip.sb/geoip" 2>/dev/null | grep -o '"country_code":"[^"]*"' | cut -d'"' -f4 || echo "")
    if [ -z "$country" ]; then
        # 尝试 ipinfo.io
        country=$(timeout 3 curl -s "https://ipinfo.io/country" 2>/dev/null | tr -d '\n' || echo "")
    fi

    if [ "$country" = "CN" ]; then
        return 0
    fi

    # 4. 最终判断：如果 baidu 可达但 Google 不可达，认为是国内
    if [ "$baidu_ok" = true ]; then
        if ! timeout 3 curl -s --head "https://www.google.com" >/dev/null 2>&1; then
            return 0
        fi
    fi

    # 默认不使用中国镜像
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

# 解析版本标识
resolve_version_tag() {
    local version="$1"
    case "$version" in
        latest) echo "latest" ;;
        dev) echo "dev-latest" ;;
        *) echo "$version" ;;
    esac
}

# 下载脚本包（支持版本参数）
download_script_package() {
    local save_path="$1"
    local version="${2:-latest}"

    # 构建 URL 列表
    local urls=()
    local tag=$(resolve_version_tag "$version")

    if [[ "$tag" == "dev-latest" ]]; then
        # 开发版：使用 dev-latest tag 下的 latest 包
        urls+=("$GITEE_BASE_URL/releases/download/dev-latest/ssl-manager-script-latest.zip")
        urls+=("$GITHUB_BASE_URL/releases/download/dev-latest/ssl-manager-script-latest.zip")
    elif [[ "$tag" == "latest" ]]; then
        # 稳定版：使用 latest tag 下的 latest 包
        urls+=("$GITEE_BASE_URL/releases/download/latest/ssl-manager-script-latest.zip")
        urls+=("$GITHUB_BASE_URL/releases/download/latest/ssl-manager-script-latest.zip")
    else
        # 指定版本：使用版本号命名的包（如 ssl-manager-script-0.0.4-beta.zip）
        urls+=("$GITEE_BASE_URL/releases/download/v$tag/ssl-manager-script-$tag.zip")
        urls+=("$GITHUB_BASE_URL/releases/download/v$tag/ssl-manager-script-$tag.zip")
    fi

    log_info "下载脚本包 (版本: $version)..."

    for url in "${urls[@]}"; do
        log_info "尝试: $url"
        if curl -fsSL --connect-timeout 10 --max-time 120 -o "$save_path" "$url" 2>/dev/null; then
            log_success "下载成功"
            return 0
        fi
    done

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
# 显示帮助
# ========================================
show_help() {
    cat <<EOF
用法: $0 [选项] [模式]

模式:
  auto    自动检测环境（默认）
  docker  使用 Docker 安装
  bt      使用宝塔面板安装

选项:
  --version, -v VERSION  指定安装版本
                         latest   最新稳定版（默认）
                         dev      最新开发版
                         x.x.x    指定版本号（自动查找）
  -h, --help             显示此帮助信息

示例:
  $0                     # 安装最新稳定版
  $0 --version dev       # 安装最新开发版
  $0 --version 1.2.0     # 安装指定版本
  $0 docker -v dev       # Docker 安装开发版
  $0 bt --version latest # 宝塔安装稳定版

环境变量:
  FORCE_CHINA_MIRROR=1   强制使用国内镜像
  FORCE_CHINA_MIRROR=0   强制使用国际源
EOF
    exit 0
}

# ========================================
# 主流程
# ========================================
main() {
    local mode="auto"
    local version="latest"

    # 解析参数
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --version|-v)
                version="$2"
                shift 2
                ;;
            -h|--help)
                show_help
                ;;
            bt|docker|auto)
                mode="$1"
                shift
                ;;
            *)
                log_error "未知参数: $1"
                show_help
                ;;
        esac
    done

    show_banner

    # 显示版本信息
    if [ "$version" != "latest" ]; then
        log_info "安装版本: $version"
    fi

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

    # 导出版本变量供子脚本使用
    export INSTALL_VERSION="$version"

    # 下载脚本包
    log_step "下载安装脚本..."
    local package_file="$TEMP_DIR/$SCRIPT_PACKAGE"
    if ! download_script_package "$package_file" "$version"; then
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
