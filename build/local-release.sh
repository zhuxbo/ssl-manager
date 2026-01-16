#!/bin/bash

# 本地发布脚本
# 用于开发测试：构建升级包并发布到本地 release 服务
#
# 用法:
#   ./local-release.sh              # 使用 version.json 中的版本号
#   ./local-release.sh 0.0.10-beta  # 指定版本号
#   ./local-release.sh --upload-only # 只上传，跳过构建

set -e

# ========================================
# 配置
# ========================================
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
BUILD_DIR="$SCRIPT_DIR"
CONFIG_FILE="$SCRIPT_DIR/local-release.conf"

# 默认配置
KEEP_VERSIONS=5

# ========================================
# 加载公共函数库
# ========================================
source "$SCRIPT_DIR/scripts/release-common.sh"

# ========================================
# 加载配置
# ========================================
load_config() {
    if [ ! -f "$CONFIG_FILE" ]; then
        log_error "配置文件不存在: $CONFIG_FILE"
        log_info "请复制 local-release.conf.example 并配置:"
        log_info "  cp $SCRIPT_DIR/local-release.conf.example $CONFIG_FILE"
        exit 1
    fi

    # 加载配置
    source "$CONFIG_FILE"

    # 验证必要配置
    if [ -z "$RELEASE_DIR" ]; then
        log_error "配置缺失: RELEASE_DIR"
        exit 1
    fi

    if [ -z "$RELEASE_URL" ]; then
        log_error "配置缺失: RELEASE_URL"
        exit 1
    fi
}

# ========================================
# 显示帮助
# ========================================
show_help() {
    cat << EOF
用法: $0 [选项] [版本号]

选项:
  --upload-only     只上传，跳过构建
  -h, --help        显示帮助

版本号获取优先级:
  1. 命令行参数指定
  2. git tag（当前 HEAD）
  3. version.json
  4. 默认值 0.0.0-dev

配置文件:
  首次使用前需创建配置文件:
  cp $SCRIPT_DIR/local-release.conf.example $SCRIPT_DIR/local-release.conf

示例:
  $0                    自动检测版本
  $0 0.0.15-beta        发布指定版本
  $0 --upload-only      只上传已构建的包
EOF
}

# ========================================
# 主流程
# ========================================
main() {
    local version=""
    local upload_only=false

    # 解析参数
    while [ $# -gt 0 ]; do
        case "$1" in
            --upload-only)
                upload_only=true
                shift
                ;;
            -h|--help)
                show_help
                exit 0
                ;;
            -*)
                log_error "未知选项: $1"
                show_help
                exit 1
                ;;
            *)
                version="$1"
                shift
                ;;
        esac
    done

    print_release_banner "SSL Manager 本地发布脚本"

    # 加载配置
    load_config

    # 如果没有指定版本，从 version.json 或 git tag 读取
    if [ -z "$version" ]; then
        # 优先从 git tag 获取
        version=$(git describe --tags --exact-match 2>/dev/null | sed 's/^v//' || true)
        # 回退到 version.json
        if [ -z "$version" ]; then
            version=$(get_version "$PROJECT_ROOT")
        fi
        # 最终回退到默认值
        if [ -z "$version" ]; then
            version="0.0.0-dev"
            log_warning "无法获取版本号，使用默认值: $version"
        fi
    fi

    # 确定通道和目标目录
    local channel=$(get_channel "$version")
    local version_dir="$RELEASE_DIR/$channel/v$version"

    log_info "版本号: $version"
    log_info "发布通道: $channel"
    log_info "目标目录: $version_dir"
    log_info "Release URL: $RELEASE_URL"
    echo ""

    # 检查 release 目录
    if [ ! -d "$RELEASE_DIR" ]; then
        log_error "release 目录不存在: $RELEASE_DIR"
        exit 1
    fi

    # 1. 构建
    local packages_dir="$BUILD_DIR/temp/packages"
    if [ "$upload_only" = false ]; then
        build_packages "$BUILD_DIR"
    else
        log_info "跳过构建，使用已有包"
    fi

    # 2. 检查构建产物
    if [ ! -d "$packages_dir" ]; then
        log_error "未找到构建产物目录: $packages_dir"
        exit 1
    fi

    # 3. 创建目标目录
    log_step "创建目标目录..."
    sudo mkdir -p "$version_dir"

    # 4. 复制包
    log_step "复制包文件..."
    local found_packages=false
    for pkg in "$packages_dir"/ssl-manager-*.zip; do
        if [ -f "$pkg" ]; then
            sudo cp "$pkg" "$version_dir/"
            log_info "已复制: $(basename "$pkg")"
            found_packages=true
        fi
    done

    if [ "$found_packages" = false ]; then
        log_error "未找到升级包"
        exit 1
    fi

    # 5. 更新 releases.json
    update_releases_json_local "$RELEASE_DIR" "$version" "$channel" "$version_dir"

    # 6. 部署脚本
    deploy_scripts_local "$PROJECT_ROOT" "$RELEASE_DIR" "$RELEASE_URL"

    # 7. 更新 latest 符号链接
    update_latest_symlinks_local "$RELEASE_DIR" "$version_dir" "$channel"

    # 8. 清理旧版本
    cleanup_old_versions_local "$RELEASE_DIR" "$channel" "$KEEP_VERSIONS"

    # 9. 设置权限
    log_step "设置权限..."
    local web_user=$(detect_web_user)
    log_info "Web 用户: $web_user"
    sudo chown -R "$web_user:$web_user" "$RELEASE_DIR/main" "$RELEASE_DIR/dev" "$RELEASE_DIR/latest" "$RELEASE_DIR/dev-latest" 2>/dev/null || true
    sudo chown "$web_user:$web_user" "$RELEASE_DIR/releases.json" "$RELEASE_DIR/install.sh" "$RELEASE_DIR/upgrade.sh" 2>/dev/null || true

    echo ""
    log_success "发布完成！"
    echo ""
    log_info "发布目录: $version_dir"
    log_info "Release URL: $RELEASE_URL"
    echo ""
    log_info "一键安装:"
    echo "  curl -fsSL $RELEASE_URL/install.sh | sudo bash"
    echo ""
    log_info "一键升级:"
    echo "  curl -fsSL $RELEASE_URL/upgrade.sh | sudo bash"
    echo ""
    log_info "手动测试:"
    echo "  # 指定版本安装"
    echo "  curl -fsSL $RELEASE_URL/install.sh | sudo bash -s -- --version $version"
    echo ""
    echo "  # 指定版本升级"
    echo "  sudo bash $PROJECT_ROOT/deploy/upgrade.sh --url $RELEASE_URL --version $version --dir /www/wwwroot/dev/product.test -y"
}

main "$@"
