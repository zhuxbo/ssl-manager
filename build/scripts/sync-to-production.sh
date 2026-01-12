#!/bin/bash

# 同步到生产仓库脚本 (Monorepo 版本)
# 将构建产物复制到 production-code 仓库

set -e

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

# 将秒数格式化为可读时长
format_secs() {
    local secs="$1"
    local h=$(( secs / 3600 ))
    local m=$(( (secs % 3600) / 60 ))
    local s=$(( secs % 60 ))
    if [ "$h" -gt 0 ]; then
        printf "%d小时%02d分%02d秒" "$h" "$m" "$s"
    elif [ "$m" -gt 0 ]; then
        printf "%d分%02d秒" "$m" "$s"
    else
        printf "%d秒" "$s"
    fi
}

# 运行 rsync 并输出精简统计摘要
run_rsync_with_stats() {
    local label="$1"; shift
    local start_ts end_ts elapsed tmpstats total changed created deleted
    tmpstats=$(mktemp)
    start_ts=$(date +%s)
    if rsync --stats "$@" >"$tmpstats" 2>&1; then
        end_ts=$(date +%s); elapsed=$(( end_ts - start_ts ))
        # 注意：rsync 输出的数字可能带逗号（如 46,144），需要匹配 [0-9,]+ 并移除逗号
        total=$(grep -Eo 'Number of files: [0-9,]+' "$tmpstats" | awk '{gsub(/,/,""); print $4}' | tail -1)
        changed=$(grep -Eo 'Number of (regular )?files transferred: [0-9,]+' "$tmpstats" | awk '{gsub(/,/,""); print $NF}' | tail -1)
        created=$(grep -Eo 'Number of created files: [0-9,]+' "$tmpstats" | awk '{gsub(/,/,""); print $5}' | tail -1)
        deleted=$(grep -Eo 'Number of deleted files: [0-9,]+' "$tmpstats" | awk '{gsub(/,/,""); print $5}' | tail -1)
        rm -f "$tmpstats"
        log_info "$label rsync: 变更 ${changed:-0} / 总 ${total:-0}, 新增 ${created:-0}, 删除 ${deleted:-0}, 用时 $(format_secs "$elapsed")"
    else
        log_error "$label rsync 执行失败"
        echo "===== rsync 输出 ====="
        cat "$tmpstats" || true
        rm -f "$tmpstats"
        return 1
    fi
}

# 从环境变量获取路径
CONFIG_FILE="${CONFIG_FILE:-/build/config.json}"
SOURCE_DIR="${SOURCE_DIR:-/source}"
WORKSPACE_DIR="${WORKSPACE_DIR:-/workspace}"
PRODUCTION_DIR="${PRODUCTION_DIR:-/workspace/production-code}"
BUILD_MODE="${BUILD_MODE:-test}"
FORCE_BUILD="${FORCE_BUILD:-false}"

log_info "开始同步到生产仓库..."

# 确保生产仓库存在
if [ ! -d "$PRODUCTION_DIR" ]; then
    log_info "初始化生产仓库..."
    PRODUCTION_REPO_URL=$(jq -r '.build.production_repo.url' "$CONFIG_FILE")

    if ! git clone "$PRODUCTION_REPO_URL" "$PRODUCTION_DIR" 2>/dev/null; then
        log_warning "克隆失败，创建本地仓库..."
        mkdir -p "$PRODUCTION_DIR"
        cd "$PRODUCTION_DIR"
        git init --initial-branch=main
        git remote add origin "$PRODUCTION_REPO_URL"
    fi
fi

cd "$PRODUCTION_DIR"

# 确保是 git 仓库
if [ ! -d .git ]; then
    git init --initial-branch=main
    PRODUCTION_REPO_URL=$(jq -r '.build.production_repo.url' "$CONFIG_FILE")
    git remote add origin "$PRODUCTION_REPO_URL" 2>/dev/null || git remote set-url origin "$PRODUCTION_REPO_URL"
fi

# 拉取最新代码
git pull origin main >/dev/null 2>&1 || true

# 确保生产仓库包含通用的 .gitignore
if [ ! -f .gitignore ]; then
    cat > .gitignore <<'EOF'
# 操作系统文件
.DS_Store
.AppleDouble
.LSOverride
__MACOSX/
.Spotlight-V100/
.Trashes
.fseventsd
Thumbs.db
ehthumbs.db
Desktop.ini
._*
$RECYCLE.BIN/

# 编辑器/工具
.idea/
.vscode/
.history/
*.log
EOF
fi

# 创建目录结构
mkdir -p backend frontend/admin frontend/user frontend/easy frontend/web nginx

# 复制后端文件
if [ "${BUILD_BACKEND:-false}" = "true" ]; then
    BACKEND_SOURCE="$WORKSPACE_DIR/backend"
    if [ -d "$BACKEND_SOURCE" ]; then
        log_info "复制后端文件（rsync，含排除与 --delete）..."
        mkdir -p "$PRODUCTION_DIR/backend"

        # 生成排除列表文件
        EXCLUDE_FILE="$(mktemp)"
        jq -r '.exclude_patterns.backend[]' "$CONFIG_FILE" 2>/dev/null >> "$EXCLUDE_FILE" || true

        # 额外排除
        cat >> "$EXCLUDE_FILE" <<'EOF'
*.md
README*
LICENSE*
CHANGELOG*
CONTRIBUTING*
.git/
.gitignore
.gitattributes
.editorconfig
.dockerignore
frontend/
nginx/
web/
vendor/**/tests/
vendor/**/Tests/
vendor/**/test/
vendor/**/docs/
vendor/**/doc/
vendor/**/.git/
vendor/**/.github/
vendor/**/examples/
vendor/**/example/
EOF

        # 直接执行 rsync（rsync 本身已优化，只复制有变化的文件）
        run_rsync_with_stats "后端" -a --delete --exclude-from="$EXCLUDE_FILE" "$BACKEND_SOURCE/" "$PRODUCTION_DIR/backend/"
        log_success "后端复制完成"
        rm -f "$EXCLUDE_FILE"
    else
        log_warning "后端目录不存在: $BACKEND_SOURCE"
    fi
fi

# 复制管理端前端
if [ "${BUILD_ADMIN:-false}" = "true" ]; then
    ADMIN_DIST="$WORKSPACE_DIR/frontend/admin/dist"
    if [ -d "$ADMIN_DIST" ]; then
        log_info "复制管理端前端文件..."
        mkdir -p "$PRODUCTION_DIR/frontend/admin"
        run_rsync_with_stats "admin" -a --delete "$ADMIN_DIST/" "$PRODUCTION_DIR/frontend/admin/"
        log_success "管理端前端复制完成"
    else
        log_error "管理端 dist 目录不存在: $ADMIN_DIST"
    fi
fi

# 复制用户端前端
if [ "${BUILD_USER:-false}" = "true" ]; then
    USER_DIST="$WORKSPACE_DIR/frontend/user/dist"
    if [ -d "$USER_DIST" ]; then
        log_info "复制用户端前端文件..."
        mkdir -p "$PRODUCTION_DIR/frontend/user"
        run_rsync_with_stats "user" -a --delete "$USER_DIST/" "$PRODUCTION_DIR/frontend/user/"
        log_success "用户端前端复制完成"
    else
        log_error "用户端 dist 目录不存在: $USER_DIST"
    fi
fi

# 复制简易端（直接从源目录复制，无需构建）
if [ "${BUILD_EASY:-false}" = "true" ]; then
    EASY_SOURCE="$SOURCE_DIR/frontend/easy"
    if [ -d "$EASY_SOURCE" ]; then
        log_info "复制简易端前端文件（源码）..."
        mkdir -p "$PRODUCTION_DIR/frontend/easy"
        run_rsync_with_stats "easy" -a --delete \
            --exclude='.git' \
            --exclude='.gitignore' \
            --exclude='.gitattributes' \
            --exclude='.editorconfig' \
            --exclude='*.md' \
            --exclude='README*' \
            --exclude='LICENSE*' \
            "$EASY_SOURCE/" "$PRODUCTION_DIR/frontend/easy/"
        log_success "简易端前端复制完成"
    else
        log_warning "简易端目录不存在: $EASY_SOURCE"
    fi
fi

# 复制 nginx 配置
if [ "${BUILD_NGINX:-false}" = "true" ]; then
    log_info "复制 nginx 配置..."
    mkdir -p nginx
    run_rsync_with_stats "nginx" -a --delete /build/nginx/ "$PRODUCTION_DIR/nginx/"
    NGINX_FILES=$(find "$PRODUCTION_DIR/nginx" -type f | wc -l)
    log_success "nginx 配置复制完成（$NGINX_FILES 个文件）"
fi

# 复制 web 静态文件
if [ "${BUILD_WEB:-false}" = "true" ]; then
    log_info "复制 web 静态文件..."
    mkdir -p frontend/web
    run_rsync_with_stats "web" -a --delete /build/web/ "$PRODUCTION_DIR/frontend/web/"

    # 覆盖 favicon.ico（如果 custom 中存在）
    CUSTOM_DIR="/build/custom"
    if [ -f "$CUSTOM_DIR/favicon.ico" ]; then
        cp "$CUSTOM_DIR/favicon.ico" "$PRODUCTION_DIR/frontend/web/public/favicon.ico"
        log_success "已覆盖 web favicon.ico"
    fi

    WEB_FILES=$(find "$PRODUCTION_DIR/frontend/web" -type f | wc -l)
    log_success "web 静态文件复制完成（$WEB_FILES 个文件）"
fi

# 获取 monorepo 提交哈希
MONOREPO_COMMIT=""
if [ -d "$SOURCE_DIR/.git" ]; then
    MONOREPO_COMMIT=$(cd "$SOURCE_DIR" && git rev-parse HEAD 2>/dev/null || echo "")
fi

# 优先从 git tag 读取版本号（构建时注入）
VERSION=""
if [ -d "$SOURCE_DIR/.git" ]; then
    GIT_TAG=$(cd "$SOURCE_DIR" && git describe --tags --exact-match HEAD 2>/dev/null || echo "")
    if [ -n "$GIT_TAG" ]; then
        VERSION=$(echo "$GIT_TAG" | sed 's/^v//')
        log_info "从 git tag 读取版本号: $VERSION"
    fi
fi

# 回退到 version.json
VERSION_FILE="$SOURCE_DIR/version.json"
if [ -z "$VERSION" ] && [ -f "$VERSION_FILE" ]; then
    VERSION=$(jq -r '.version // "1.0.0"' "$VERSION_FILE")
    log_info "从 version.json 读取版本号: $VERSION"
elif [ -z "$VERSION" ]; then
    VERSION="1.0.0"
    log_warning "无法获取版本号，使用默认版本 $VERSION"
fi

# 读取发布通道
if [ -f "$VERSION_FILE" ]; then
    RELEASE_CHANNEL=$(jq -r '.channel // "main"' "$VERSION_FILE")
else
    RELEASE_CHANNEL="main"
fi

# 生成构建信息
BUILD_TIME=$(date -Iseconds)
cat > "$PRODUCTION_DIR/config.json" <<EOF
{
  "version": "$VERSION",
  "channel": "$RELEASE_CHANNEL",
  "build_time": "$BUILD_TIME",
  "build_module": "${BUILD_MODULE:-all}",
  "php_version": "$(php -v | head -n1)",
  "node_version": "$(node -v)",
  "composer_version": "$(composer --version --no-ansi)",
  "monorepo_commit": "$MONOREPO_COMMIT"
}
EOF

# 更新后端 version.php 配置（写入构建时信息）
VERSION_PHP="$PRODUCTION_DIR/backend/config/version.php"
if [ -f "$VERSION_PHP" ]; then
    log_info "更新 version.php 配置..."
    # 在容器中创建临时 .env 写入构建信息
    BACKEND_ENV="$PRODUCTION_DIR/backend/.env.build"
    cat > "$BACKEND_ENV" <<EOF
APP_VERSION=$VERSION
APP_BUILD_TIME=$BUILD_TIME
APP_BUILD_COMMIT=$MONOREPO_COMMIT
APP_RELEASE_CHANNEL=$RELEASE_CHANNEL
EOF
    log_info "已生成 .env.build 文件"
fi

log_success "config.json 已生成"
log_info "版本: $VERSION"
log_info "通道: $RELEASE_CHANNEL"
log_info "Monorepo commit: ${MONOREPO_COMMIT:-N/A}"
