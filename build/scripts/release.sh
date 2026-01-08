#!/bin/bash

# SSL证书管理系统 - Gitee Release 发布脚本
# 将构建好的 release 包上传到 Gitee Releases

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BUILD_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }
log_warning() { echo -e "${YELLOW}[WARN]${NC} $1"; }

# 默认配置
GITEE_API="https://gitee.com/api/v5"
PACKAGE_DIR="${PACKAGE_DIR:-$BUILD_DIR/temp/packages}"

# 显示帮助
show_help() {
    cat <<EOF
SSL证书管理系统 - Gitee Release 发布脚本

用法: $0 [选项]

选项:
  --version VERSION     版本号（必需）
  --token TOKEN         Gitee Access Token（或使用环境变量 GITEE_ACCESS_TOKEN）
  --owner OWNER         仓库所有者（默认从 config.json 读取）
  --repo REPO           仓库名称（默认从 config.json 读取）
  --package-dir DIR     包目录（默认: $PACKAGE_DIR）
  --prerelease          标记为预发布版本
  --changelog FILE      更新日志文件
  --dry-run             模拟运行，不实际发布
  -h, --help            显示此帮助信息

环境变量:
  GITEE_ACCESS_TOKEN    Gitee API Token

示例:
  $0 --version 1.0.0 --token your_token
  GITEE_ACCESS_TOKEN=xxx $0 --version 1.0.0

EOF
    exit 0
}

# 参数
VERSION=""
GITEE_TOKEN="${GITEE_ACCESS_TOKEN:-}"
GITEE_OWNER=""
GITEE_REPO=""
PRERELEASE=false
CHANGELOG_FILE=""
DRY_RUN=false

# 解析参数
while [[ $# -gt 0 ]]; do
    case "$1" in
        --version)
            VERSION="$2"
            shift 2
            ;;
        --token)
            GITEE_TOKEN="$2"
            shift 2
            ;;
        --owner)
            GITEE_OWNER="$2"
            shift 2
            ;;
        --repo)
            GITEE_REPO="$2"
            shift 2
            ;;
        --package-dir)
            PACKAGE_DIR="$2"
            shift 2
            ;;
        --prerelease)
            PRERELEASE=true
            shift
            ;;
        --changelog)
            CHANGELOG_FILE="$2"
            shift 2
            ;;
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        -h|--help)
            show_help
            ;;
        *)
            log_error "未知参数: $1"
            exit 1
            ;;
    esac
done

# 验证必需参数
if [ -z "$VERSION" ]; then
    log_error "请指定版本号: --version VERSION"
    exit 1
fi

if [ -z "$GITEE_TOKEN" ]; then
    log_error "请提供 Gitee Access Token: --token TOKEN 或设置环境变量 GITEE_ACCESS_TOKEN"
    exit 1
fi

# 从 config.json 读取默认值
CONFIG_FILE="$BUILD_DIR/config.json"
if [ -f "$CONFIG_FILE" ]; then
    if [ -z "$GITEE_OWNER" ]; then
        GITEE_OWNER=$(jq -r '.build.release.gitee.owner // "zhuxbo"' "$CONFIG_FILE")
    fi
    if [ -z "$GITEE_REPO" ]; then
        GITEE_REPO=$(jq -r '.build.release.gitee.repo // "cert-manager"' "$CONFIG_FILE")
    fi
fi

# 默认值
GITEE_OWNER="${GITEE_OWNER:-zhuxbo}"
GITEE_REPO="${GITEE_REPO:-cert-manager}"

# 检查包目录
if [ ! -d "$PACKAGE_DIR" ]; then
    log_error "包目录不存在: $PACKAGE_DIR"
    exit 1
fi

# 检查必需的包文件
FULL_PACKAGE="$PACKAGE_DIR/ssl-manager-full-$VERSION.zip"
UPGRADE_PACKAGE="$PACKAGE_DIR/ssl-manager-upgrade-$VERSION.zip"
SCRIPT_PACKAGE="$PACKAGE_DIR/ssl-manager-script-$VERSION.zip"
MANIFEST_FILE="$PACKAGE_DIR/manifest.json"

if [ ! -f "$FULL_PACKAGE" ]; then
    log_error "完整包不存在: $FULL_PACKAGE"
    exit 1
fi

if [ ! -f "$UPGRADE_PACKAGE" ]; then
    log_error "升级包不存在: $UPGRADE_PACKAGE"
    exit 1
fi

if [ ! -f "$SCRIPT_PACKAGE" ]; then
    log_warning "脚本包不存在: $SCRIPT_PACKAGE"
    SCRIPT_PACKAGE=""
fi

if [ ! -f "$MANIFEST_FILE" ]; then
    log_error "manifest.json 不存在: $MANIFEST_FILE"
    exit 1
fi

# 读取更新日志
CHANGELOG=""
if [ -n "$CHANGELOG_FILE" ] && [ -f "$CHANGELOG_FILE" ]; then
    CHANGELOG=$(cat "$CHANGELOG_FILE")
elif [ -f "$BUILD_DIR/../CHANGELOG.md" ]; then
    # 尝试从 CHANGELOG.md 提取当前版本的内容
    CHANGELOG=$(sed -n "/^## \[$VERSION\]/,/^## \[/p" "$BUILD_DIR/../CHANGELOG.md" | head -n -1)
fi

if [ -z "$CHANGELOG" ]; then
    CHANGELOG="Release v$VERSION"
fi

log_info "============================================"
log_info "Gitee Release 发布"
log_info "============================================"
log_info "版本号:    v$VERSION"
log_info "仓库:      $GITEE_OWNER/$GITEE_REPO"
log_info "预发布:    $PRERELEASE"
log_info "包目录:    $PACKAGE_DIR"
log_info "============================================"
echo ""

if [ "$DRY_RUN" = true ]; then
    log_warning "模拟运行模式 - 不会实际发布"
    echo ""
fi

# 创建 Release
create_release() {
    local tag_name="v$VERSION"
    local release_name="v$VERSION"
    local body="$CHANGELOG"
    local prerelease_str="false"
    [ "$PRERELEASE" = true ] && prerelease_str="true"

    log_info "创建 Release: $tag_name"

    if [ "$DRY_RUN" = true ]; then
        log_info "[DRY-RUN] 将创建 Release: $tag_name"
        echo "RELEASE_ID=dry-run-123"
        return
    fi

    local response
    response=$(curl -s -X POST "$GITEE_API/repos/$GITEE_OWNER/$GITEE_REPO/releases" \
        -H "Content-Type: application/json" \
        -d "{
            \"access_token\": \"$GITEE_TOKEN\",
            \"tag_name\": \"$tag_name\",
            \"name\": \"$release_name\",
            \"body\": $(echo "$body" | jq -Rs .),
            \"prerelease\": $prerelease_str,
            \"target_commitish\": \"main\"
        }")

    local release_id
    release_id=$(echo "$response" | jq -r '.id // empty')

    if [ -z "$release_id" ]; then
        log_error "创建 Release 失败"
        echo "Response: $response"
        exit 1
    fi

    log_success "Release 创建成功: ID=$release_id"
    echo "RELEASE_ID=$release_id"
}

# 上传附件
upload_asset() {
    local release_id="$1"
    local file_path="$2"
    local file_name
    file_name=$(basename "$file_path")

    log_info "上传附件: $file_name"

    if [ "$DRY_RUN" = true ]; then
        log_info "[DRY-RUN] 将上传: $file_path"
        return 0
    fi

    local response
    response=$(curl -s -X POST "$GITEE_API/repos/$GITEE_OWNER/$GITEE_REPO/releases/$release_id/attach_files" \
        -F "access_token=$GITEE_TOKEN" \
        -F "file=@$file_path")

    local asset_id
    asset_id=$(echo "$response" | jq -r '.id // empty')

    if [ -z "$asset_id" ]; then
        log_error "上传附件失败: $file_name"
        echo "Response: $response"
        return 1
    fi

    local download_url
    download_url=$(echo "$response" | jq -r '.browser_download_url // empty')
    log_success "上传成功: $file_name"
    log_info "  下载地址: $download_url"
    return 0
}

# 主流程
main() {
    # 步骤 1: 创建 Release
    log_info "步骤 1: 创建 Release"
    local release_result
    release_result=$(create_release)
    local release_id
    release_id=$(echo "$release_result" | grep "RELEASE_ID=" | cut -d= -f2)

    if [ -z "$release_id" ]; then
        log_error "获取 Release ID 失败"
        exit 1
    fi

    echo ""

    # 步骤 2: 上传附件
    log_info "步骤 2: 上传版本化附件"

    upload_asset "$release_id" "$FULL_PACKAGE"
    upload_asset "$release_id" "$UPGRADE_PACKAGE"
    if [ -n "$SCRIPT_PACKAGE" ]; then
        upload_asset "$release_id" "$SCRIPT_PACKAGE"
    fi
    upload_asset "$release_id" "$MANIFEST_FILE"

    echo ""

    # 注意: latest Release 由 GitHub CI 自动创建，此脚本仅用于 Gitee 手动发布

    # 完成
    log_info "============================================"
    log_success "发布完成！"
    log_info "============================================"
    log_info "Release: https://gitee.com/$GITEE_OWNER/$GITEE_REPO/releases/v$VERSION"
    log_info "============================================"
}

main
