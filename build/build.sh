#!/bin/bash

# Monorepo 构建脚本 - 容器化构建主控脚本
# 从 monorepo 直接构建，不需要多仓库同步

set -Eeuo pipefail
trap 'echo -e "\033[0;31m[ERROR]\033[0m 命令失败: ${BASH_COMMAND} (行号: ${LINENO})"' ERR

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

# 日志函数
log_info() { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }
log_warning() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_step() { echo -e "${CYAN}[STEP]${NC} $1"; }

# 记录脚本起始时间
SCRIPT_START_TIME=$(date +%s)

# 将秒数格式化为中文可读时长
human_duration() {
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

# 获取脚本目录和 monorepo 根目录
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MONOREPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
CONFIG_FILE="$SCRIPT_DIR/config.json"
BUILD_ENV="$SCRIPT_DIR/build.env"
CUSTOM_DIR="$SCRIPT_DIR/custom"
TEMP_DIR="$SCRIPT_DIR/temp"

# 创建临时目录结构
mkdir -p "$TEMP_DIR"/{reports,production-code,caches/pnpm-store,caches/composer-cache}

# 默认参数
BUILD_MODULE="all"
BUILD_MODE="local"          # 默认本地构建
RELEASE_CHANNEL=""          # release 通道: main 或 dev
FORCE_BUILD="false"
REBUILD_IMAGE="false"
COMPARE="false"
CLEAR_CACHE="false"
CREATE_PACKAGE="false"      # 是否创建安装包

# 显示帮助信息
show_help() {
    cat <<EOF
Monorepo 构建脚本 - 容器化构建

用法: $0 [选项] [模块]

模块:
  all         构建所有模块（默认）
  api         仅构建后端 API
  admin       仅构建管理端前端
  user        仅构建用户端前端
  easy        处理简易端（无构建，直接同步发布）
  nginx       仅更新 nginx 配置
  web         仅更新 web 静态文件

选项:
  --release [main|dev]  发布模式，推送到远程仓库
                        main: 正式版（main 分支）
                        dev:  开发版（dev 分支）
                        不指定通道则默认为 main
  --package             构建完成后创建安装包（完整包+升级包）
  --force-build         强制构建（即使已是最新）
  --clear-cache         构建前清空依赖缓存
  --rebuild-image       强制重建 Docker 镜像
  --compare             构建成功后输出与远程产物差异摘要
  -h, --help            显示此帮助信息

示例:
  $0                             # 本地构建所有模块
  $0 api                         # 本地构建后端
  $0 --package                   # 本地构建并创建安装包
  $0 --release                   # 发布正式版（main）
  $0 --release dev               # 发布开发版
  $0 --release main --package    # 发布正式版并创建安装包
EOF
    exit 0
}

# 解析命令行参数
ARGS=("$@")
i=0
while [ $i -lt ${#ARGS[@]} ]; do
    arg="${ARGS[$i]}"
    case "$arg" in
        -h|--help)
            show_help
            ;;
        --release)
            BUILD_MODE="release"
            # 检查下一个参数是否是 release 通道
            next_idx=$((i + 1))
            if [ $next_idx -lt ${#ARGS[@]} ]; then
                next_arg="${ARGS[$next_idx]}"
                if [[ "$next_arg" =~ ^(main|dev)$ ]]; then
                    RELEASE_CHANNEL="$next_arg"
                    i=$next_idx
                fi
            fi
            # 默认通道为 main
            [ -z "$RELEASE_CHANNEL" ] && RELEASE_CHANNEL="main"
            ;;
        --force-build)
            FORCE_BUILD="true"
            ;;
        --clear-cache)
            CLEAR_CACHE="true"
            ;;
        --rebuild-image)
            REBUILD_IMAGE="true"
            ;;
        --compare)
            COMPARE="true"
            ;;
        --package)
            CREATE_PACKAGE="true"
            ;;
        --*)
            log_error "未知选项: $arg"
            echo "使用 --help 查看帮助"
            exit 1
            ;;
        *)
            # 模块名
            if [[ "$arg" =~ ^(all|api|admin|user|easy|nginx|web)$ ]]; then
                BUILD_MODULE="$arg"
            else
                log_error "无效的模块名: $arg"
                echo "有效模块: all, api, admin, user, easy, nginx, web"
                exit 1
            fi
            ;;
    esac
    i=$((i + 1))
done

# 显示构建配置
echo ""
log_info "============================================"
log_info "Monorepo 构建系统"
log_info "============================================"
log_info "构建模块: $BUILD_MODULE"
if [ "$BUILD_MODE" = "release" ]; then
    log_info "构建模式: release ($RELEASE_CHANNEL)"
else
    log_info "构建模式: $BUILD_MODE"
fi
log_info "强制构建: $FORCE_BUILD"
log_info "重建镜像: $REBUILD_IMAGE"
log_info "Monorepo: $MONOREPO_ROOT"
log_info "============================================"
echo ""

# 检查 Docker
if ! command -v docker &> /dev/null; then
    log_error "Docker 未安装"
    log_info "请先安装 Docker: https://docs.docker.com/get-docker/"
    exit 1
fi

# 检查 Docker 服务
if ! docker info >/dev/null 2>&1; then
    log_error "Docker 服务未运行"
    log_info "请启动 Docker 服务"
    exit 1
fi

log_success "Docker 检查通过"
echo ""

# 如果 custom/build.env 存在，覆盖默认的 build.env
if [ -f "$CUSTOM_DIR/build.env" ]; then
    log_info "使用 custom/build.env 覆盖默认配置"
    BUILD_ENV="$CUSTOM_DIR/build.env"
fi

# 检查 build.env
if [ ! -f "$BUILD_ENV" ]; then
    log_error "未找到 $BUILD_ENV"
    log_info "请创建 $BUILD_ENV，示例见 README"
    exit 1
fi

# 可选：清理依赖缓存
if [ "$CLEAR_CACHE" = "true" ]; then
    log_step "清理依赖缓存与构建产物"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    CACHE_TARGETS=(
        "$TEMP_DIR/caches/pnpm-store"
        "$TEMP_DIR/caches/composer-cache"
        "$TEMP_DIR/.dep_hashes"
        "$TEMP_DIR/workspace"
    )

    for path in "${CACHE_TARGETS[@]}"; do
        if [ -e "$path" ]; then
            log_info "移除: $path"
            rm -rf "$path"
        fi
    done
    log_success "缓存清理完成"
    echo ""
fi

# SSH 认证：仅发布模式推送时需要
NEED_SSH=false
USE_AGENT=false
if [ "$BUILD_MODE" = "release" ]; then
    NEED_SSH=true
    # 检测 ssh-agent
    if [ -z "${SSH_AUTH_SOCK:-}" ] || [ ! -S "${SSH_AUTH_SOCK}" ]; then
        log_info "未检测到 ssh-agent，尝试启动..."
        eval "$(ssh-agent -s)" >/dev/null
    fi

    if [ -n "${SSH_AUTH_SOCK:-}" ] && [ -S "${SSH_AUTH_SOCK}" ]; then
        USE_AGENT=true
        # 若无身份，尝试自动加载配置的私钥
        # shellcheck disable=SC1090
        source "$BUILD_ENV"
        if ssh-add -l >/dev/null 2>&1 && ssh-add -l 2>&1 | grep -q "no identities"; then
            CAND=()
            if [ -n "${GITEE_SSH_KEY:-}" ]; then
                if [[ "$GITEE_SSH_KEY" == ~* ]]; then
                    CAND+=("${GITEE_SSH_KEY/#\~/$HOME}")
                else
                    CAND+=("$GITEE_SSH_KEY")
                fi
            fi
            CAND+=("$HOME/.ssh/gitee_id_rsa" "$HOME/.ssh/id_ed25519" "$HOME/.ssh/id_rsa")

            for key in "${CAND[@]}"; do
                if [ -f "$key" ] && [ -r "$key" ]; then
                    log_info "尝试加载私钥: $key"
                    if ssh-add -q "$key" </dev/null >/dev/null 2>&1; then
                        log_success "已加载私钥: $key"
                        break
                    fi
                fi
            done

            if ! ssh-add -l >/dev/null 2>&1 || ssh-add -l 2>&1 | grep -q "no identities"; then
                log_error "未能自动加载 SSH 私钥。请手动执行：ssh-add ~/.ssh/id_ed25519"
                exit 1
            fi
        fi
        log_success "SSH 认证已就绪"
    else
        log_error "无法启动 ssh-agent，请手动执行：eval \"\$(ssh-agent)\" && ssh-add"
        exit 1
    fi
else
    log_info "本地构建模式，不需要 SSH 认证"
fi

# 读取配置
# shellcheck disable=SC1090
source "$BUILD_ENV"
: "${BASE_IMAGE_NAME:=cnssl-build-base}"
: "${BASE_IMAGE_TAG:=ubuntu-24.04}"
: "${BUILD_IMAGE_NAME:=cnssl-build}"
: "${BUILD_IMAGE_TAG:=latest}"
: "${REBUILD_DAYS:=30}"

BASE_IMAGE_FULL="$BASE_IMAGE_NAME:$BASE_IMAGE_TAG"
BUILD_IMAGE_FULL="$BUILD_IMAGE_NAME:$BUILD_IMAGE_TAG"

to_epoch() {
    local ts="$1"
    if date -d "$ts" +%s >/dev/null 2>&1; then
        date -d "$ts" +%s
        return
    fi
    local base_no_frac="${ts%%.*}"
    if [[ "$ts" =~ [+-][0-9]{2}:[0-9]{2} ]]; then
        local off_part="${ts#*${base_no_frac}}"
        local off_compact
        off_compact=$(echo "$off_part" | sed -E 's/^([+-][0-9]{2}):([0-9]{2}).*$/\1\2/' )
        if date -j -f "%Y-%m-%dT%H:%M:%S%z" "${base_no_frac}${off_compact}" "+%s" >/dev/null 2>&1; then
            date -j -f "%Y-%m-%dT%H:%M:%S%z" "${base_no_frac}${off_compact}" "+%s"
            return
        fi
    fi
    if [[ "$ts" == *Z ]]; then
        if date -j -u -f "%Y-%m-%dT%H:%M:%S" "$base_no_frac" "+%s" >/dev/null 2>&1; then
            date -j -u -f "%Y-%m-%dT%H:%M:%S" "$base_no_frac" "+%s"
            return
        fi
    fi
    echo 0
}

check_image_age() {
    local image_full="$1"
    local max_days="$2"

    if ! docker image inspect "$image_full" >/dev/null 2>&1; then
        echo "not_found"
        return
    fi

    local created
    created=$(docker image inspect "$image_full" --format='{{.Created}}')
    local created_timestamp
    created_timestamp=$(to_epoch "$created")
    local now_timestamp
    now_timestamp=$(date "+%s")
    if [ "$created_timestamp" -eq 0 ]; then
        echo "ok"
        return
    fi
    local age_days=$(( (now_timestamp - created_timestamp) / 86400 ))
    if [ "$age_days" -gt "$max_days" ]; then
        echo "outdated"
    else
        echo "ok"
    fi
}

# 构建基础镜像
BASE_IMAGE_STATUS=$(check_image_age "$BASE_IMAGE_FULL" "$REBUILD_DAYS")

if [ "$REBUILD_IMAGE" = "true" ] || [ "$BASE_IMAGE_STATUS" = "not_found" ] || [ "$BASE_IMAGE_STATUS" = "outdated" ]; then
    log_step "构建基础镜像: $BASE_IMAGE_FULL"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

    if [ "$BASE_IMAGE_STATUS" = "outdated" ]; then
        log_warning "基础镜像超过 $REBUILD_DAYS 天，重建中..."
    fi

    docker build \
        -f "$SCRIPT_DIR/Dockerfile.base" \
        -t "$BASE_IMAGE_FULL" \
        "$SCRIPT_DIR"

    log_success "基础镜像构建完成"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
else
    log_info "基础镜像已存在: $BASE_IMAGE_FULL"
    echo ""
fi

# 构建构建镜像
log_step "构建构建镜像: $BUILD_IMAGE_FULL"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

docker build \
    -f "$SCRIPT_DIR/Dockerfile.build" \
    --build-arg BASE_IMAGE="$BASE_IMAGE_FULL" \
    -t "$BUILD_IMAGE_FULL" \
    "$SCRIPT_DIR"

log_success "构建镜像构建完成"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# 准备构建报告文件名
REPORT_TIMESTAMP=$(date "+%Y%m%d-%H%M%S")
BUILD_REPORT="$TEMP_DIR/reports/build-$REPORT_TIMESTAMP.log"
ERROR_REPORT="$TEMP_DIR/reports/error-$REPORT_TIMESTAMP.log"

log_info "构建日志: $BUILD_REPORT"
log_info "错误日志: $ERROR_REPORT"
echo ""

# 启动容器执行构建
log_step "启动容器执行构建"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# 构建 docker run 选项
DOCKER_OPTS=(--rm --memory=2g)

# 挂载 monorepo 源代码（只读）
DOCKER_OPTS+=( -v "$MONOREPO_ROOT:/source:ro" )

# 挂载工作目录
DOCKER_OPTS+=( -v "$TEMP_DIR:/workspace" )

# 挂载构建脚本
DOCKER_OPTS+=( -v "$SCRIPT_DIR/scripts:/build/scripts:ro" )

# 挂载 custom 目录（如果存在）
if [ -d "$CUSTOM_DIR" ]; then
    DOCKER_OPTS+=( -v "$CUSTOM_DIR:/build/custom:ro" )
    log_info "已挂载 custom 目录"
fi

# 传递构建环境变量
DOCKER_OPTS+=( -e BUILD_MODULE="$BUILD_MODULE" )
DOCKER_OPTS+=( -e BUILD_MODE="$BUILD_MODE" )
DOCKER_OPTS+=( -e RELEASE_CHANNEL="$RELEASE_CHANNEL" )
DOCKER_OPTS+=( -e FORCE_BUILD="$FORCE_BUILD" )

# 依赖缓存复用
DOCKER_OPTS+=( -e COMPOSER_CACHE_DIR=/composer/cache )
DOCKER_OPTS+=( -v "$TEMP_DIR/caches/composer-cache:/composer/cache" )
DOCKER_OPTS+=( -e PNPM_STORE_DIR=/pnpm/store )
DOCKER_OPTS+=( -v "$TEMP_DIR/caches/pnpm-store:/pnpm/store" )

# SSH：仅生产模式需要推送时
if [ "$NEED_SSH" = true ]; then
    if [ "$USE_AGENT" = true ]; then
        DOCKER_OPTS+=( -e SSH_AUTH_SOCK=/ssh-agent )
        DOCKER_OPTS+=( -v "${SSH_AUTH_SOCK}:/ssh-agent" )
        DOCKER_OPTS+=( -e USE_SSH_AGENT=true )
    fi
    if [ -f "$HOME/.ssh/known_hosts" ]; then
        DOCKER_OPTS+=( -v "$HOME/.ssh/known_hosts:/root/.ssh/known_hosts:ro" )
    fi
    # 私钥文件后备
    EXP_KEY="${GITEE_SSH_KEY:-}"
    if [ -n "$EXP_KEY" ]; then
        [[ "$EXP_KEY" == ~* ]] && EXP_KEY="${EXP_KEY/#\~/$HOME}"
        if [ -f "$EXP_KEY" ]; then
            DOCKER_OPTS+=( -v "$EXP_KEY:/root/.ssh/id_gitee:ro" )
            DOCKER_OPTS+=( -e GIT_SSH_COMMAND="ssh -i /root/.ssh/id_gitee -o StrictHostKeyChecking=accept-new" )
        fi
    fi
fi

# 运行容器
log_info "容器将在构建完成后自动销毁"
set +e
docker run "${DOCKER_OPTS[@]}" "$BUILD_IMAGE_FULL" 2>&1 | tee "$BUILD_REPORT"
RUN_STATUS=${PIPESTATUS[0]}
set -e

if [ "$RUN_STATUS" -eq 0 ]; then
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    log_success "构建流程完成！"
    echo ""
    log_info "构建报告: $BUILD_REPORT"

    # 显示构建摘要
    if [ -f "$TEMP_DIR/production-code/config.json" ]; then
        VERSION=$(awk -F '"' '/"version"/ {for(i=1;i<=NF;i++){if($i=="version"){print $(i+2); exit}}}' "$TEMP_DIR/production-code/config.json" 2>/dev/null || echo "")
        BUILD_TIME=$(awk -F '"' '/"build_time"/ {for(i=1;i<=NF;i++){if($i=="build_time"){print $(i+2); exit}}}' "$TEMP_DIR/production-code/config.json" 2>/dev/null || echo "")
        [ -n "$VERSION" ] && log_info "构建版本: $VERSION"
        [ -n "$BUILD_TIME" ] && log_info "构建时间: $BUILD_TIME"
        END_TIME=$(date +%s)
        ELAPSED=$(( END_TIME - SCRIPT_START_TIME ))
        log_info "构建用时: $(human_duration "$ELAPSED")"
        log_info "生产代码: $TEMP_DIR/production-code"
    fi

    # 可选：构建产物与远程差异对比
    if [ "$COMPARE" = "true" ]; then
        echo ""
        log_step "产物差异对比"
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
        if bash "$SCRIPT_DIR/scripts/compare-production.sh" --show 200; then
            log_success "差异对比完成"
        else
            log_warning "差异对比执行返回非零（可能存在差异或网络问题）"
        fi
        echo ""
    fi

    # 可选：创建安装包
    if [ "$CREATE_PACKAGE" = "true" ]; then
        echo ""
        log_step "创建安装包"
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
        PACKAGE_CHANNEL="${RELEASE_CHANNEL:-main}"
        if bash "$SCRIPT_DIR/scripts/package.sh" --channel "$PACKAGE_CHANNEL"; then
            log_success "安装包创建完成"
            log_info "安装包位于: $TEMP_DIR/packages"
        else
            log_warning "安装包创建失败"
        fi
        echo ""
    fi

    # 本地构建模式提示
    if [ "$BUILD_MODE" = "local" ]; then
        echo ""
        log_info "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
        log_info "本地构建完成，产物位于: $TEMP_DIR/production-code"
        log_info "如需发布正式版: ./build.sh --release main"
        log_info "如需发布开发版: ./build.sh --release dev"
        log_info "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    fi

    exit 0
else
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    log_error "构建失败"

    # 提取错误信息
    {
        echo "==== Matched Errors ===="
        grep -Ei "error|failed|fatal|exception|panic|traceback" "$BUILD_REPORT" || true
        echo -e "\n==== Last 200 Lines ===="
        tail -n 200 "$BUILD_REPORT" || true
    } > "$ERROR_REPORT" 2>/dev/null || true

    if [ -s "$ERROR_REPORT" ]; then
        log_error "错误信息已保存到: $ERROR_REPORT"
        echo ""
        log_info "错误详情:"
        cat "$ERROR_REPORT"
    fi

    END_TIME=$(date +%s)
    ELAPSED=$(( END_TIME - SCRIPT_START_TIME ))
    log_info "本次构建用时: $(human_duration "$ELAPSED")"
    log_warning "提示：可使用 --clear-cache 清理依赖缓存后再试"

    exit 1
fi
