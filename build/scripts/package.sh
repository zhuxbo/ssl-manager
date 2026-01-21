#!/bin/bash

# SSL证书管理系统 - 打包脚本
# 生成完整安装包和升级包

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

log_info() { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }
log_warning() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_step() { echo -e "${CYAN}[STEP]${NC} $1"; }

# 默认路径
BUILD_DIR="${BUILD_DIR:-$(cd "$SCRIPT_DIR/.." && pwd)}"
PRODUCTION_DIR="${PRODUCTION_DIR:-$BUILD_DIR/temp/production-code}"
OUTPUT_DIR="${OUTPUT_DIR:-$BUILD_DIR/temp/packages}"
CHANNEL="${RELEASE_CHANNEL:-}"
BUILD_CONFIG="$BUILD_DIR/config.json"
VERSION=""

# 显示帮助
show_help() {
    cat <<EOF
SSL证书管理系统 - 打包脚本

用法: $0 [选项]

选项:
  --version VER     指定版本号（优先级最高）
  --source DIR      指定生产代码目录（默认: $PRODUCTION_DIR）
  --output DIR      指定输出目录（默认: $OUTPUT_DIR）
  --channel NAME    指定发布通道 main|dev（自动根据版本号判断）
  -h, --help        显示此帮助信息

版本号获取优先级:
  1. --version 参数
  2. config.json 中的 version 字段

通道自动判断:
  - 包含 -beta/-alpha/-rc/-dev 的版本 → dev 通道
  - 其他版本 → main 通道

EOF
    exit 0
}

# 解析参数
while [[ $# -gt 0 ]]; do
    case "$1" in
        --version)
            VERSION="$2"
            shift 2
            ;;
        --source)
            PRODUCTION_DIR="$2"
            shift 2
            ;;
        --output)
            OUTPUT_DIR="$2"
            shift 2
            ;;
        --channel)
            CHANNEL="$2"
            shift 2
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

# 检查生产代码目录
if [ ! -d "$PRODUCTION_DIR" ]; then
    log_error "生产代码目录不存在: $PRODUCTION_DIR"
    log_info "请先运行构建: ./build.sh"
    exit 1
fi

# 检查 config.json
if [ ! -f "$PRODUCTION_DIR/config.json" ]; then
    log_error "未找到 config.json"
    exit 1
fi

# 从 build/config.json 读取排除列表到临时文件
# 用法: create_exclude_file <package_type> <output_file> [prefix_filter]
# package_type: full 或 upgrade
# prefix_filter: 可选，过滤指定前缀的路径（如 "backend/" 或 "frontend/admin/"）
create_exclude_file() {
    local pkg_type="$1"
    local output_file="$2"
    local prefix_filter="${3:-}"

    # 清空文件
    > "$output_file"

    if [ -f "$BUILD_CONFIG" ] && command -v jq &> /dev/null; then
        # 首先添加 backend 的通用排除规则（生产无关文件，仅当无前缀过滤或过滤 backend 时）
        if [ -z "$prefix_filter" ] || [[ "$prefix_filter" == "backend/" ]]; then
            jq -r '.exclude_patterns.backend[]?' "$BUILD_CONFIG" 2>/dev/null >> "$output_file" || true
        fi

        # 然后添加包类型特定的排除规则
        if [ -z "$prefix_filter" ]; then
            # 无过滤，直接添加所有规则
            jq -r ".package.$pkg_type.exclude[]?" "$BUILD_CONFIG" 2>/dev/null >> "$output_file" || true
        else
            # 有前缀过滤，只提取匹配前缀的规则并去除前缀
            jq -r ".package.$pkg_type.exclude[]?" "$BUILD_CONFIG" 2>/dev/null | while read -r line; do
                if [[ "$line" == "$prefix_filter"* ]]; then
                    # 去除前缀后添加
                    echo "${line#$prefix_filter}"
                fi
            done >> "$output_file"
        fi
    fi

    # 如果配置读取失败，使用默认值
    if [ ! -s "$output_file" ]; then
        # 默认的生产无关文件排除
        cat >> "$output_file" <<EOF
.git/
.github/
.gitignore
.gitattributes
.editorconfig
.pint.json
.cursor/
.idea/
.vscode/
.DS_Store
.env
.env.*
.phpunit.cache/
tests/
phpunit.xml
phpstan.neon
*.md
README*
LICENSE*
EOF
        # 包类型特定排除
        if [ "$pkg_type" = "full" ]; then
            cat >> "$output_file" <<EOF
vendor/
deploy/
storage/upgrades/
storage/backups/
storage/logs/*.log
storage/framework/cache/*
EOF
        elif [ "$pkg_type" = "upgrade" ]; then
            cat >> "$output_file" <<EOF
storage/*
bootstrap/cache/*
vendor/*
EOF
        fi
    fi
}

# 读取版本号（如果未通过参数指定）
if [ -z "$VERSION" ]; then
    VERSION=$(grep -o '"version"[[:space:]]*:[[:space:]]*"[^"]*"' "$PRODUCTION_DIR/config.json" | head -1 | sed 's/.*"\([^"]*\)"$/\1/')
    if [ -z "$VERSION" ]; then
        log_error "无法读取版本号，请使用 --version 参数指定"
        exit 1
    fi
fi

# 自动判断通道（如果未通过参数指定）
if [ -z "$CHANNEL" ]; then
    if [[ "$VERSION" =~ -(dev|alpha|beta|rc) ]]; then
        CHANNEL="dev"
    else
        CHANNEL="main"
    fi
fi

# 创建输出目录（清空旧文件）
mkdir -p "$OUTPUT_DIR"
rm -f "$OUTPUT_DIR"/ssl-manager-*.zip "$OUTPUT_DIR"/manifest.json 2>/dev/null || true

log_info "============================================"
log_info "SSL证书管理系统 - 打包"
log_info "============================================"
log_info "版本号:   $VERSION"
log_info "发布通道: $CHANNEL"
log_info "源目录:   $PRODUCTION_DIR"
log_info "输出目录: $OUTPUT_DIR"
log_info "============================================"
echo ""

# 包文件名
FULL_PACKAGE="ssl-manager-full-$VERSION.zip"
UPGRADE_PACKAGE="ssl-manager-upgrade-$VERSION.zip"
MANIFEST_FILE="manifest.json"

# 临时工作目录
WORK_DIR=$(mktemp -d)
trap "rm -rf $WORK_DIR" EXIT

# 阶段 1: 创建完整安装包
log_step "阶段 1: 创建完整安装包"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

FULL_DIR="$WORK_DIR/full"
mkdir -p "$FULL_DIR"

# 创建排除列表文件
FULL_EXCLUDE_FILE="$WORK_DIR/full-exclude.txt"
create_exclude_file "full" "$FULL_EXCLUDE_FILE"

# 复制文件，使用配置的排除列表
rsync -a --exclude-from="$FULL_EXCLUDE_FILE" "$PRODUCTION_DIR/" "$FULL_DIR/"

# 计算源目录路径（BUILD_DIR 的父目录是项目根目录）
PROJECT_ROOT="$(cd "$BUILD_DIR/.." && pwd)"

# 确保 easy 目录存在（easy 是纯静态文件，直接从源目录复制）
EASY_SOURCE="$PROJECT_ROOT/frontend/easy"
if [ ! -d "$FULL_DIR/frontend/easy" ] && [ -d "$EASY_SOURCE" ]; then
    log_info "从源目录复制 easy 前端..."
    mkdir -p "$FULL_DIR/frontend/easy"
    rsync -a --exclude='.git*' --exclude='*.md' --exclude='LICENSE*' \
        "$EASY_SOURCE/" "$FULL_DIR/frontend/easy/"
fi

# 复制 web 目录（自定义静态页面）
WEB_SOURCE="$BUILD_DIR/web"
if [ -d "$WEB_SOURCE" ]; then
    log_info "复制 web 静态页面..."
    mkdir -p "$FULL_DIR/frontend/web"
    rsync -a --exclude='.git*' "$WEB_SOURCE/" "$FULL_DIR/frontend/web/"
fi

# 复制 nginx 目录（宝塔部署需要）
NGINX_SOURCE="$BUILD_DIR/nginx"
if [ -d "$NGINX_SOURCE" ]; then
    log_info "复制 nginx 配置..."
    mkdir -p "$FULL_DIR/nginx"
    rsync -a --exclude='.git*' "$NGINX_SOURCE/" "$FULL_DIR/nginx/"
fi

# 确保前端目录完整
for app in admin user easy web; do
    if [ -d "$FULL_DIR/frontend/$app" ]; then
        log_info "已包含前端: $app"
    fi
done

# 检查 nginx 目录
if [ -d "$FULL_DIR/nginx" ]; then
    log_info "已包含 nginx 配置"
fi

# 创建 Laravel 运行时必需的空目录结构（zip -r 会保留空目录）
mkdir -p "$FULL_DIR/backend/storage/"{app/{public,private},framework/{cache,sessions,views},logs,pay}
mkdir -p "$FULL_DIR/backend/bootstrap/cache"
mkdir -p "$FULL_DIR/backend/vendor"

# 创建 version.json（运行时版本信息）
cat > "$FULL_DIR/version.json" <<EOF
{
  "version": "$VERSION",
  "channel": "$CHANNEL",
  "build_time": "$(date -u "+%Y-%m-%dT%H:%M:%SZ")"
}
EOF

# 创建 manifest.json（包元信息）
cat > "$FULL_DIR/manifest.json" <<EOF
{
  "version": "$VERSION",
  "channel": "$CHANNEL",
  "build_time": "$(date -u "+%Y-%m-%dT%H:%M:%SZ")"
}
EOF

# 打包
cd "$WORK_DIR"
zip -rq "$OUTPUT_DIR/$FULL_PACKAGE" full -x "*/.git/*" -x "*/.git*"
FULL_SIZE=$(du -h "$OUTPUT_DIR/$FULL_PACKAGE" | cut -f1)
FULL_SHA256=$(sha256sum "$OUTPUT_DIR/$FULL_PACKAGE" | cut -d' ' -f1)

log_success "完整包: $FULL_PACKAGE ($FULL_SIZE)"
echo ""

# 阶段 2: 创建升级包
log_step "阶段 2: 创建升级包"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

UPGRADE_DIR="$WORK_DIR/upgrade"
mkdir -p "$UPGRADE_DIR"

# 升级包只包含代码，不包含 vendor、配置和用户数据
# 创建后端排除列表文件（过滤 backend/ 前缀的规则）
UPGRADE_BACKEND_EXCLUDE="$WORK_DIR/upgrade-backend-exclude.txt"
create_exclude_file "upgrade" "$UPGRADE_BACKEND_EXCLUDE" "backend/"

mkdir -p "$UPGRADE_DIR/backend"
rsync -a --exclude-from="$UPGRADE_BACKEND_EXCLUDE" "$PRODUCTION_DIR/backend/" "$UPGRADE_DIR/backend/"

# 升级包不需要 vendor 目录（升级时会保留现有的 vendor）

# 前端：保持 frontend/ 目录结构
# 使用统一的 upgrade.exclude 配置，过滤 frontend/ 前缀的规则
if [ -d "$PRODUCTION_DIR/frontend" ]; then
    mkdir -p "$UPGRADE_DIR/frontend"
    for app in admin user; do
        if [ -d "$PRODUCTION_DIR/frontend/$app" ]; then
            # 创建该前端应用的排除列表（过滤 frontend/$app/ 前缀的规则）
            FRONTEND_EXCLUDE_FILE="$WORK_DIR/upgrade-frontend-$app-exclude.txt"
            create_exclude_file "upgrade" "$FRONTEND_EXCLUDE_FILE" "frontend/$app/"

            rsync -a --exclude-from="$FRONTEND_EXCLUDE_FILE" "$PRODUCTION_DIR/frontend/$app/" "$UPGRADE_DIR/frontend/$app/"
            log_info "升级包已包含前端: $app（已排除用户配置）"
        fi
    done
fi

# 确保 easy 目录存在（从源目录复制）
if [ ! -d "$UPGRADE_DIR/frontend/easy" ] && [ -d "$EASY_SOURCE" ]; then
    log_info "升级包：从源目录复制 easy 前端..."
    mkdir -p "$UPGRADE_DIR/frontend/easy"
    # 创建 easy 的排除列表（过滤 frontend/easy/ 前缀的规则）
    EASY_EXCLUDE_FILE="$WORK_DIR/upgrade-frontend-easy-exclude.txt"
    create_exclude_file "upgrade" "$EASY_EXCLUDE_FILE" "frontend/easy/"
    # 添加通用排除（easy 从源目录复制，需要额外排除）
    echo ".git*" >> "$EASY_EXCLUDE_FILE"
    echo "*.md" >> "$EASY_EXCLUDE_FILE"
    echo "LICENSE*" >> "$EASY_EXCLUDE_FILE"

    rsync -a --exclude-from="$EASY_EXCLUDE_FILE" "$EASY_SOURCE/" "$UPGRADE_DIR/frontend/easy/"
    log_info "升级包已包含前端: easy（已排除用户配置）"
fi

# 复制 nginx 目录（路由配置，升级时需要更新）
if [ -d "$PRODUCTION_DIR/nginx" ]; then
    cp -r "$PRODUCTION_DIR/nginx" "$UPGRADE_DIR/"
    log_info "升级包已包含 nginx 配置"
fi

# 注意：升级包不包含 web 目录，避免覆盖用户自定义页面

# 创建 version.json（运行时版本信息）
cat > "$UPGRADE_DIR/version.json" <<EOF
{
  "version": "$VERSION",
  "channel": "$CHANNEL",
  "build_time": "$(date -u "+%Y-%m-%dT%H:%M:%SZ")"
}
EOF

# 创建 manifest.json（升级服务需要此文件验证包有效性）
cat > "$UPGRADE_DIR/manifest.json" <<EOF
{
  "version": "$VERSION",
  "channel": "$CHANNEL",
  "build_time": "$(date -u "+%Y-%m-%dT%H:%M:%SZ")"
}
EOF

# 创建升级说明
cat > "$UPGRADE_DIR/UPGRADE.md" <<EOF
# SSL证书管理系统 升级包

版本: $VERSION
通道: $CHANNEL
打包时间: $(date "+%Y-%m-%d %H:%M:%S")

## 升级步骤

1. 备份当前版本
2. 解压升级包覆盖文件
3. 安装 PHP 依赖: composer install --no-dev
4. 运行数据库迁移: php artisan migrate --force
5. 清理缓存: php artisan optimize:clear
6. 重启服务

## 注意事项

- 升级包不包含 vendor 目录，需要运行 composer install 安装依赖
- 升级包不包含 .env 配置文件，不会覆盖现有配置
- 升级包不包含 storage 目录，不会影响上传的文件
- 建议在升级前备份数据库
- 如使用 install.php 升级，会自动检测并安装 Composer 依赖

EOF

# 打包
cd "$WORK_DIR"
zip -rq "$OUTPUT_DIR/$UPGRADE_PACKAGE" upgrade -x "*/.git/*" -x "*/.git*"
UPGRADE_SIZE=$(du -h "$OUTPUT_DIR/$UPGRADE_PACKAGE" | cut -d'	' -f1)
UPGRADE_SHA256=$(sha256sum "$OUTPUT_DIR/$UPGRADE_PACKAGE" | cut -d' ' -f1)

log_success "升级包: $UPGRADE_PACKAGE ($UPGRADE_SIZE)"
echo ""

# 阶段 3: 创建脚本部署包
log_step "阶段 3: 创建脚本部署包"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

SCRIPT_PACKAGE="ssl-manager-script-$VERSION.zip"
# 从项目根目录获取 deploy（BUILD_DIR 的父目录）
PROJECT_ROOT="$(cd "$BUILD_DIR/.." && pwd)"
SCRIPT_DIR_SRC="$PROJECT_ROOT/deploy"

if [ -d "$SCRIPT_DIR_SRC" ]; then
    SCRIPT_PKG_DIR="$WORK_DIR/script-deploy"
    mkdir -p "$SCRIPT_PKG_DIR/scripts"

    # 复制脚本文件
    cp "$SCRIPT_DIR_SRC/scripts/"*.sh "$SCRIPT_PKG_DIR/scripts/" 2>/dev/null || true
    cp "$SCRIPT_DIR_SRC/install.sh" "$SCRIPT_PKG_DIR/" 2>/dev/null || true
    cp "$SCRIPT_DIR_SRC/upgrade.sh" "$SCRIPT_PKG_DIR/" 2>/dev/null || true

    # 复制 docker 部署配置（docker-install.sh 需要）
    if [ -d "$SCRIPT_DIR_SRC/docker" ]; then
        cp -r "$SCRIPT_DIR_SRC/docker" "$SCRIPT_PKG_DIR/"
        log_info "已包含 docker 部署配置"
    fi

    # 创建使用说明
    cat > "$SCRIPT_PKG_DIR/README.md" <<EOF
# SSL证书管理系统 - 部署脚本

版本: $VERSION
打包时间: $(date "+%Y-%m-%d %H:%M:%S")

## 安装

\`\`\`bash
chmod +x install.sh
./install.sh
\`\`\`

## 升级

\`\`\`bash
chmod +x upgrade.sh
./upgrade.sh
\`\`\`

## 文件说明

- \`install.sh\` - 安装脚本（自动检测环境）
- \`upgrade.sh\` - 升级脚本
- \`scripts/bt-install.sh\` - 宝塔面板安装
- \`scripts/docker-install.sh\` - Docker 交互式安装
- \`scripts/common.sh\` - 公共函数库
- \`scripts/bt-deps.sh\` - 宝塔依赖检测

注意：Nginx 配置已统一打包在完整包的 nginx 目录中。
EOF

    # 打包
    cd "$WORK_DIR"
    zip -rq "$OUTPUT_DIR/$SCRIPT_PACKAGE" script-deploy
    SCRIPT_SIZE=$(du -h "$OUTPUT_DIR/$SCRIPT_PACKAGE" | cut -d'	' -f1)
    SCRIPT_SHA256=$(sha256sum "$OUTPUT_DIR/$SCRIPT_PACKAGE" | cut -d' ' -f1)

    log_success "脚本包: $SCRIPT_PACKAGE ($SCRIPT_SIZE)"
else
    log_warning "未找到 deploy 目录，跳过脚本包"
    SCRIPT_SIZE=""
    SCRIPT_SHA256=""
fi
echo ""

# 阶段 4: 生成 manifest.json
log_step "阶段 4: 生成 manifest.json"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

BUILD_TIME=$(date -u "+%Y-%m-%dT%H:%M:%SZ")

if [ -n "$SCRIPT_SHA256" ]; then
    cat > "$OUTPUT_DIR/$MANIFEST_FILE" <<EOF
{
  "version": "$VERSION",
  "channel": "$CHANNEL",
  "build_time": "$BUILD_TIME",
  "packages": {
    "full": {
      "filename": "$FULL_PACKAGE",
      "sha256": "$FULL_SHA256",
      "size": "$FULL_SIZE"
    },
    "upgrade": {
      "filename": "$UPGRADE_PACKAGE",
      "sha256": "$UPGRADE_SHA256",
      "size": "$UPGRADE_SIZE"
    },
    "script": {
      "filename": "$SCRIPT_PACKAGE",
      "sha256": "$SCRIPT_SHA256",
      "size": "$SCRIPT_SIZE"
    }
  },
  "changelog": "",
  "min_version": "",
  "notes": ""
}
EOF
else
    cat > "$OUTPUT_DIR/$MANIFEST_FILE" <<EOF
{
  "version": "$VERSION",
  "channel": "$CHANNEL",
  "build_time": "$BUILD_TIME",
  "packages": {
    "full": {
      "filename": "$FULL_PACKAGE",
      "sha256": "$FULL_SHA256",
      "size": "$FULL_SIZE"
    },
    "upgrade": {
      "filename": "$UPGRADE_PACKAGE",
      "sha256": "$UPGRADE_SHA256",
      "size": "$UPGRADE_SIZE"
    }
  },
  "changelog": "",
  "min_version": "",
  "notes": ""
}
EOF
fi

log_success "manifest.json 已生成"
echo ""

# 完成
log_info "============================================"
log_success "打包完成！"
log_info "============================================"
log_info "输出目录: $OUTPUT_DIR"
log_info ""
log_info "生成的文件:"
log_info "  - $FULL_PACKAGE ($FULL_SIZE)"
log_info "  - $UPGRADE_PACKAGE ($UPGRADE_SIZE)"
if [ -n "$SCRIPT_SHA256" ]; then
    log_info "  - $SCRIPT_PACKAGE ($SCRIPT_SIZE)"
fi
log_info "  - $MANIFEST_FILE"
log_info "============================================"
