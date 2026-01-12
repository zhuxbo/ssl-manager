#!/bin/bash

# SSL证书管理系统 - 版本发布脚本
# 更新 version.json、提交、打 tag、推送

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

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

# 显示帮助
show_help() {
    cat <<EOF
SSL证书管理系统 - 版本发布脚本

用法: $0 <版本号> [选项]

参数:
  版本号              例如: 0.0.10-beta, 1.0.0

选项:
  --force             强制发布（删除现有 tag/release 后重新创建）
  --no-push           不推送到远程
  --no-tag            不创建 tag
  -h, --help          显示此帮助信息

分支规则:
  正式版 (1.0.0)              → main 分支
  测试版 (x.x.x-beta/dev/rc)  → dev 分支

示例:
  $0 0.0.10-beta              # 发布到 dev 分支
  $0 1.0.0                    # 发布到 main 分支
  $0 0.0.10-beta --force      # 强制重新发布（删除旧 tag 和 release）
  $0 0.0.10-beta --no-push    # 仅本地提交和打 tag

EOF
    exit 0
}

# 参数
VERSION=""
DO_PUSH=true
DO_TAG=true
FORCE=false

# 解析参数
while [[ $# -gt 0 ]]; do
    case "$1" in
        --force)
            FORCE=true
            shift
            ;;
        --no-push)
            DO_PUSH=false
            shift
            ;;
        --no-tag)
            DO_TAG=false
            shift
            ;;
        -h|--help)
            show_help
            ;;
        -*)
            log_error "未知选项: $1"
            exit 1
            ;;
        *)
            if [ -z "$VERSION" ]; then
                VERSION="$1"
            else
                log_error "多余的参数: $1"
                exit 1
            fi
            shift
            ;;
    esac
done

# 验证版本号
if [ -z "$VERSION" ]; then
    log_error "请指定版本号"
    echo ""
    show_help
fi

# 验证版本号格式
if ! echo "$VERSION" | grep -qE '^[0-9]+\.[0-9]+\.[0-9]+(-[a-zA-Z0-9.]+)?$'; then
    log_error "无效的版本号格式: $VERSION"
    log_info "正确格式: X.Y.Z 或 X.Y.Z-suffix (如 1.0.0, 0.0.10-beta)"
    exit 1
fi

cd "$PROJECT_ROOT"

# 根据版本号选择分支
case "$VERSION" in
    *-dev*|*-alpha*|*-beta*|*-rc*)
        BRANCH="dev"
        ;;
    *)
        BRANCH="main"
        ;;
esac
log_info "目标分支: $BRANCH"

# 检查当前分支是否匹配
CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)
if [ "$CURRENT_BRANCH" != "$BRANCH" ]; then
    log_warning "当前分支 ($CURRENT_BRANCH) 与目标分支 ($BRANCH) 不同"
    read -p "是否切换到 $BRANCH 分支？(Y/n) " -n 1 -r
    echo ""
    if [[ ! $REPLY =~ ^[Nn]$ ]]; then
        git checkout "$BRANCH"
        git pull origin "$BRANCH" --rebase || true
        log_success "已切换到 $BRANCH 分支"
    fi
fi

# 检查工作区是否干净（除了 version.json）
DIRTY_FILES=$(git status --porcelain | grep -v "version.json" || true)
if [ -n "$DIRTY_FILES" ]; then
    log_warning "工作区有未提交的更改:"
    echo "$DIRTY_FILES"
    echo ""
    read -p "是否继续？(y/N) " -n 1 -r
    echo ""
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# 检查 tag 是否已存在
TAG_NAME="v$VERSION"
if git tag -l "$TAG_NAME" | grep -q "$TAG_NAME"; then
    if [ "$FORCE" = true ]; then
        log_warning "Tag $TAG_NAME 已存在，强制模式将删除重建"
        # 删除远程 tag
        git push origin ":refs/tags/$TAG_NAME" 2>/dev/null || true
        # 删除本地 tag
        git tag -d "$TAG_NAME"
        log_success "已删除旧 tag $TAG_NAME"
    else
        log_error "Tag $TAG_NAME 已存在（使用 --force 强制重新发布）"
        exit 1
    fi
fi

log_info "============================================"
log_info "版本发布"
log_info "============================================"
log_info "版本号:  $VERSION"
log_info "Tag:     $TAG_NAME"
log_info "分支:    $BRANCH"
log_info "推送:    $DO_PUSH"
[ "$FORCE" = true ] && log_info "强制模式: 是"
log_info "============================================"
echo ""

# 步骤 1: 更新 version.json
log_info "步骤 1: 更新 version.json"
VERSION_FILE="$PROJECT_ROOT/version.json"

# 判断通道
case "$VERSION" in
    *-dev*|*-alpha*|*-beta*|*-rc*)
        CHANNEL="dev"
        ;;
    *)
        CHANNEL="main"
        ;;
esac

cat > "$VERSION_FILE" <<EOF
{
  "version": "$VERSION",
  "channel": "$CHANNEL"
}
EOF

log_success "version.json 已更新: $VERSION (channel: $CHANNEL)"

# 步骤 2: 提交更改（如果有变化）
log_info "步骤 2: 提交更改"
git add "$VERSION_FILE"
if git diff --cached --quiet; then
    log_info "version.json 无变化，跳过提交"
else
    git commit -m "chore: 版本升级至 $VERSION"
    log_success "提交成功"
fi

# 步骤 3: 创建 tag
if [ "$DO_TAG" = true ]; then
    log_info "步骤 3: 创建 tag"
    git tag "$TAG_NAME"
    log_success "Tag $TAG_NAME 创建成功"
fi

# 步骤 4: 推送
if [ "$DO_PUSH" = true ]; then
    log_info "步骤 4: 推送到远程"
    git push origin "$BRANCH"
    log_success "分支 $BRANCH 推送成功"

    if [ "$DO_TAG" = true ]; then
        git push origin "$TAG_NAME"
        log_success "Tag $TAG_NAME 推送成功"
    fi
fi

echo ""
log_info "============================================"
log_success "发布完成！"
log_info "============================================"
log_info "版本: $VERSION"
log_info "Tag:  $TAG_NAME"
log_info "============================================"
