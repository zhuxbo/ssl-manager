#!/bin/bash

# SSL Manager 在线升级脚本
# 用法:
#   curl -fsSL https://gitee.com/zhuxbo/cert-manager/raw/main/deploy/upgrade.sh | bash
#   curl -fsSL https://gitee.com/zhuxbo/cert-manager/raw/main/deploy/upgrade.sh | bash -s -- --version 1.0.0

set -e

# ========================================
# 配置
# ========================================
REPO_OWNER="zhuxbo"
REPO_NAME="cert-manager"
GITEE_BASE_URL="https://gitee.com/$REPO_OWNER/$REPO_NAME"
GITHUB_BASE_URL="https://github.com/$REPO_OWNER/$REPO_NAME"
TEMP_DIR="/tmp/ssl-manager-upgrade-$$"
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
# 工具函数
# ========================================
cleanup() {
    if [ -d "$TEMP_DIR" ]; then
        rm -rf "$TEMP_DIR"
    fi
}
trap cleanup EXIT

get_timestamp() {
    date '+%Y%m%d_%H%M%S'
}

# 全局变量：自动确认
AUTO_YES=false

confirm() {
    local message="$1"
    local default="${2:-n}"

    # 自动确认模式
    if [ "$AUTO_YES" = true ]; then
        return 0
    fi

    if [ "$default" = "y" ]; then
        read -p "$message [Y/n]: " choice < /dev/tty
        case "$choice" in
            n|N) return 1 ;;
            *) return 0 ;;
        esac
    else
        read -p "$message [y/N]: " choice < /dev/tty
        case "$choice" in
            y|Y) return 0 ;;
            *) return 1 ;;
        esac
    fi
}

file_sha256() {
    local file="$1"
    if command -v sha256sum &> /dev/null; then
        sha256sum "$file" | cut -d' ' -f1
    elif command -v shasum &> /dev/null; then
        shasum -a 256 "$file" | cut -d' ' -f1
    else
        openssl dgst -sha256 "$file" | awk '{print $NF}'
    fi
}

is_china_server() {
    if [ -n "$FORCE_CHINA_MIRROR" ]; then
        [ "$FORCE_CHINA_MIRROR" = "1" ] && return 0 || return 1
    fi

    # 云服务商检测
    local aliyun_region=$(timeout 1 curl -s "http://100.100.100.200/latest/meta-data/region-id" 2>/dev/null || echo "")
    if [ -n "$aliyun_region" ] && [[ "$aliyun_region" =~ ^cn- ]]; then
        return 0
    fi

    # Baidu 可达 + Google 不可达
    if timeout 2 curl -s --head "https://www.baidu.com" >/dev/null 2>&1; then
        if ! timeout 3 curl -s --head "https://www.google.com" >/dev/null 2>&1; then
            return 0
        fi
    fi

    return 1
}

check_docker_compose() {
    if command -v docker-compose &> /dev/null; then
        echo "docker-compose"
        return 0
    elif docker compose version &> /dev/null; then
        echo "docker compose"
        return 0
    fi
    return 1
}

# 版本比较（v1 > v2 返回 0）
version_gt() {
    local v1=$(echo "$1" | sed 's/^v//' | sed 's/-.*//')
    local v2=$(echo "$2" | sed 's/^v//' | sed 's/-.*//')

    if [ "$v1" = "$v2" ]; then
        return 1
    fi

    local sorted=$(printf '%s\n%s' "$v1" "$v2" | sort -V | tail -1)
    [ "$sorted" = "$v1" ] && return 0 || return 1
}

# ========================================
# 检测函数
# ========================================

# 检测安装目录和部署模式
detect_install() {
    # 如果已手动指定目录，验证并检测模式
    if [ -n "$INSTALL_DIR" ]; then
        # 优先检测 .ssl-manager 标记文件，回退到 artisan
        if [ -f "$INSTALL_DIR/backend/.ssl-manager" ] || [ -f "$INSTALL_DIR/backend/artisan" ]; then
            if [ -f "$INSTALL_DIR/docker-compose.yml" ]; then
                DEPLOY_MODE="docker"
            else
                DEPLOY_MODE="bt"
            fi
            return 0
        fi
        log_error "指定目录无效: $INSTALL_DIR"
        return 1
    fi

    DEPLOY_MODE=""
    INSTALL_DIR=""

    # 搜索所有安装目录
    local found_dirs=()

    # 预设目录快速检测
    local preset_dirs=(
        "/opt/ssl-manager"
        "/opt/cert-manager"
        "/www/wwwroot/ssl-manager"
        "/www/wwwroot/cert-manager"
    )

    for dir in "${preset_dirs[@]}"; do
        # 优先检测 .ssl-manager，回退到 artisan
        if [ -f "$dir/backend/.ssl-manager" ] || [ -f "$dir/backend/artisan" ]; then
            found_dirs+=("$dir")
        fi
    done

    # 系统范围搜索（补充非预设目录）
    while IFS= read -r marker; do
        [ -z "$marker" ] && continue
        local dir=$(dirname "$marker" | xargs dirname)
        # 避免重复
        local already_found=false
        for fd in "${found_dirs[@]}"; do
            [ "$fd" = "$dir" ] && already_found=true && break
        done
        $already_found || found_dirs+=("$dir")
    done < <(find /opt /www/wwwroot /home -maxdepth 4 -name ".ssl-manager" -path "*/backend/*" 2>/dev/null)

    # 根据找到的数量处理
    if [ ${#found_dirs[@]} -eq 0 ]; then
        return 1
    elif [ ${#found_dirs[@]} -eq 1 ]; then
        INSTALL_DIR="${found_dirs[0]}"
        [ -f "$INSTALL_DIR/docker-compose.yml" ] && DEPLOY_MODE="docker" || DEPLOY_MODE="bt"
        return 0
    else
        # 多个安装，让用户选择
        log_info "检测到多个 SSL Manager 安装："
        for i in "${!found_dirs[@]}"; do
            local dir="${found_dirs[$i]}"
            local mode="宝塔"
            [ -f "$dir/docker-compose.yml" ] && mode="Docker"
            echo "  $((i+1)). $dir [$mode]"
        done

        while true; do
            read -p "请选择 (1-${#found_dirs[@]}): " choice < /dev/tty
            if [[ "$choice" =~ ^[0-9]+$ ]] && [ "$choice" -ge 1 ] && [ "$choice" -le ${#found_dirs[@]} ]; then
                INSTALL_DIR="${found_dirs[$((choice-1))]}"
                [ -f "$INSTALL_DIR/docker-compose.yml" ] && DEPLOY_MODE="docker" || DEPLOY_MODE="bt"
                return 0
            fi
            log_error "无效选择"
        done
    fi
}

# 获取当前版本
get_current_version() {
    local config_json="$INSTALL_DIR/config.json"
    local backend_config="$INSTALL_DIR/backend/config.json"

    # 优先从 config.json 读取
    if [ -f "$config_json" ]; then
        local ver=$(grep -o '"version"[[:space:]]*:[[:space:]]*"[^"]*"' "$config_json" | head -1 | cut -d'"' -f4)
        if [ -n "$ver" ]; then
            echo "$ver"
            return 0
        fi
    fi

    if [ -f "$backend_config" ]; then
        local ver=$(grep -o '"version"[[:space:]]*:[[:space:]]*"[^"]*"' "$backend_config" | head -1 | cut -d'"' -f4)
        if [ -n "$ver" ]; then
            echo "$ver"
            return 0
        fi
    fi

    echo "unknown"
}

# ========================================
# 下载函数
# ========================================

download_upgrade_package() {
    local version="$1"
    local save_path="$2"

    local urls=()

    if [[ "$version" == "latest" ]]; then
        urls+=("$GITEE_BASE_URL/releases/download/latest/ssl-manager-full-latest.zip")
        urls+=("$GITHUB_BASE_URL/releases/download/latest/ssl-manager-full-latest.zip")
    elif [[ "$version" == "dev" ]]; then
        urls+=("$GITEE_BASE_URL/releases/download/dev-latest/ssl-manager-full-latest.zip")
        urls+=("$GITHUB_BASE_URL/releases/download/dev-latest/ssl-manager-full-latest.zip")
    else
        urls+=("$GITEE_BASE_URL/releases/download/v$version/ssl-manager-full-$version.zip")
        urls+=("$GITHUB_BASE_URL/releases/download/v$version/ssl-manager-full-$version.zip")
    fi

    for url in "${urls[@]}"; do
        log_info "尝试下载: $url"
        if curl -fsSL --connect-timeout 10 --max-time 300 -o "$save_path" "$url" 2>/dev/null; then
            log_success "下载成功"
            return 0
        fi
    done

    log_error "下载失败"
    return 1
}

# ========================================
# 升级流程
# ========================================

# 创建备份
create_backup() {
    # 日志输出到 stderr，避免被 $() 捕获
    log_step "创建备份..." >&2

    local backup_dir="$INSTALL_DIR/storage/backups"
    local timestamp=$(get_timestamp)
    local backup_path="$backup_dir/$timestamp"

    mkdir -p "$backup_path/code"

    # 备份代码目录（排除 storage、vendor）
    log_info "备份代码..." >&2
    rsync -a --exclude='storage' --exclude='vendor' --exclude='node_modules' \
        "$INSTALL_DIR/backend/" "$backup_path/code/backend/" 2>/dev/null || \
        cp -r "$INSTALL_DIR/backend" "$backup_path/code/" 2>/dev/null || true

    # 备份配置文件
    [ -f "$INSTALL_DIR/.env" ] && cp "$INSTALL_DIR/.env" "$backup_path/"
    [ -f "$INSTALL_DIR/config.json" ] && cp "$INSTALL_DIR/config.json" "$backup_path/"
    [ -f "$INSTALL_DIR/backend/.env" ] && cp "$INSTALL_DIR/backend/.env" "$backup_path/backend.env"
    [ -f "$INSTALL_DIR/backend/config.json" ] && cp "$INSTALL_DIR/backend/config.json" "$backup_path/backend.config.json"

    # 记录备份信息
    echo "{\"version\": \"$(get_current_version)\", \"timestamp\": \"$timestamp\"}" > "$backup_path/backup.json"

    log_success "备份完成: $backup_path" >&2
    # 只输出路径到 stdout，供调用者捕获
    echo "$backup_path"
}

# 执行升级
perform_upgrade() {
    local target_version="$1"
    local upgrade_file="$2"

    log_step "开始升级到版本 $target_version"

    # 1. 记录旧版本 composer.lock hash
    local old_composer_hash=""
    if [ -f "$INSTALL_DIR/backend/composer.lock" ]; then
        old_composer_hash=$(file_sha256 "$INSTALL_DIR/backend/composer.lock")
        log_info "当前 composer.lock hash: ${old_composer_hash:0:16}..."
    fi

    # 2. 创建备份
    local backup_path=$(create_backup)

    # 3. 进入维护模式（必须在移动 vendor 之前）
    log_step "进入维护模式..."
    if [ "$DEPLOY_MODE" = "docker" ]; then
        local compose_cmd=$(check_docker_compose)
        cd "$INSTALL_DIR"
        $compose_cmd exec -T php php artisan down --retry=60 || true
    else
        cd "$INSTALL_DIR/backend"
        php artisan down --retry=60 || true
    fi

    # 4. 提取需要保留的文件到临时目录
    log_step "保留关键文件..."
    local preserve_dir="$TEMP_DIR/preserve"
    mkdir -p "$preserve_dir"

    # 保留 .env（不保留 config.json，升级需要更新版本号）
    [ -f "$INSTALL_DIR/backend/.env" ] && cp "$INSTALL_DIR/backend/.env" "$preserve_dir/"
    # 保留 storage
    if [ -d "$INSTALL_DIR/backend/storage" ]; then
        cp -r "$INSTALL_DIR/backend/storage" "$preserve_dir/"
    fi
    # 保留 vendor（加速升级）
    if [ -d "$INSTALL_DIR/backend/vendor" ]; then
        log_info "保留 vendor 目录（加速升级）..."
        mv "$INSTALL_DIR/backend/vendor" "$preserve_dir/"
    fi

    # 5. 删除旧代码
    log_step "清理旧代码..."
    # 只删除后端代码目录（保留 storage 已移走）
    rm -rf "$INSTALL_DIR/backend/app"
    rm -rf "$INSTALL_DIR/backend/bootstrap"
    rm -rf "$INSTALL_DIR/backend/config"
    rm -rf "$INSTALL_DIR/backend/database"
    rm -rf "$INSTALL_DIR/backend/public"
    rm -rf "$INSTALL_DIR/backend/resources"
    rm -rf "$INSTALL_DIR/backend/routes"
    rm -rf "$INSTALL_DIR/backend/tests"
    rm -f "$INSTALL_DIR/backend/artisan"
    rm -f "$INSTALL_DIR/backend/composer.json"
    rm -f "$INSTALL_DIR/backend/composer.lock"

    # 删除前端目录
    rm -rf "$INSTALL_DIR/admin" 2>/dev/null || true
    rm -rf "$INSTALL_DIR/user" 2>/dev/null || true

    # 6. 解压升级包
    log_step "解压升级包..."
    local extract_dir="$TEMP_DIR/extract"
    mkdir -p "$extract_dir"
    unzip -q "$upgrade_file" -d "$extract_dir"

    # 查找解压后的目录结构
    local src_dir="$extract_dir"
    if [ -d "$extract_dir/ssl-manager" ]; then
        src_dir="$extract_dir/ssl-manager"
    elif [ -d "$extract_dir/cert-manager" ]; then
        src_dir="$extract_dir/cert-manager"
    fi

    # 7. 复制新代码（使用 /. 确保复制隐藏文件如 .ssl-manager）
    log_step "应用新版本..."
    if [ -d "$src_dir/backend" ]; then
        cp -r "$src_dir/backend/." "$INSTALL_DIR/backend/"
    fi

    # 复制前端
    for app in admin user; do
        if [ -d "$src_dir/$app" ]; then
            mkdir -p "$INSTALL_DIR/$app"
            cp -r "$src_dir/$app"/* "$INSTALL_DIR/$app/" 2>/dev/null || true
        fi
    done

    # 复制根目录配置
    [ -f "$src_dir/config.json" ] && cp "$src_dir/config.json" "$INSTALL_DIR/"

    # 8. 恢复保留的文件
    log_step "恢复保留文件..."
    [ -f "$preserve_dir/.env" ] && cp "$preserve_dir/.env" "$INSTALL_DIR/backend/"
    # 注意：不恢复 config.json，使用升级包中的新版本

    # 恢复 storage
    if [ -d "$preserve_dir/storage" ]; then
        rm -rf "$INSTALL_DIR/backend/storage"
        mv "$preserve_dir/storage" "$INSTALL_DIR/backend/"
    fi

    # 恢复 vendor
    if [ -d "$preserve_dir/vendor" ]; then
        mv "$preserve_dir/vendor" "$INSTALL_DIR/backend/"
    fi

    # 9. 检测依赖变化，决定是否运行 composer install
    log_step "检测依赖变化..."
    local new_composer_hash=""
    if [ -f "$INSTALL_DIR/backend/composer.lock" ]; then
        new_composer_hash=$(file_sha256 "$INSTALL_DIR/backend/composer.lock")
        log_info "新版本 composer.lock hash: ${new_composer_hash:0:16}..."
    fi

    local need_composer=false
    if [ -z "$old_composer_hash" ] || [ "$old_composer_hash" != "$new_composer_hash" ]; then
        need_composer=true
        log_info "依赖已变化，需要更新"
    else
        log_info "依赖未变化，跳过 composer install"
    fi

    if [ "$need_composer" = true ]; then
        log_step "安装 Composer 依赖..."
        if [ "$DEPLOY_MODE" = "docker" ]; then
            cd "$INSTALL_DIR"
            if is_china_server; then
                $compose_cmd exec -T php composer config repo.packagist composer https://mirrors.tencent.com/composer/
            fi
            $compose_cmd exec -T php composer install --no-dev --optimize-autoloader
        else
            cd "$INSTALL_DIR/backend"
            if is_china_server; then
                composer config repo.packagist composer https://mirrors.tencent.com/composer/
            fi
            COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader
        fi
    fi

    # 10. 运行数据库迁移
    log_step "运行数据库迁移..."
    if [ "$DEPLOY_MODE" = "docker" ]; then
        cd "$INSTALL_DIR"
        $compose_cmd exec -T php php artisan migrate --force
    else
        cd "$INSTALL_DIR/backend"
        php artisan migrate --force
    fi

    # 11. 清理缓存
    log_step "清理缓存..."
    if [ "$DEPLOY_MODE" = "docker" ]; then
        cd "$INSTALL_DIR"
        $compose_cmd exec -T php php artisan config:cache || true
        $compose_cmd exec -T php php artisan route:cache || true
        $compose_cmd exec -T php php artisan view:cache || true
    else
        cd "$INSTALL_DIR/backend"
        php artisan config:cache || true
        php artisan route:cache || true
        php artisan view:cache || true
    fi

    # 12. 完整性校验
    log_step "完整性校验..."
    local check_passed=true

    if [ ! -f "$INSTALL_DIR/backend/.env" ]; then
        log_warning ".env 文件不存在"
        check_passed=false
    fi

    if [ ! -d "$INSTALL_DIR/backend/storage/logs" ]; then
        log_warning "storage/logs 目录不存在"
        mkdir -p "$INSTALL_DIR/backend/storage/logs"
    fi

    if [ ! -f "$INSTALL_DIR/backend/vendor/autoload.php" ]; then
        log_warning "vendor/autoload.php 不存在"
        check_passed=false
    fi

    if [ "$check_passed" = true ]; then
        log_success "完整性校验通过"
    else
        log_warning "部分校验未通过，请检查"
    fi

    # 13. 退出维护模式
    log_step "退出维护模式..."
    if [ "$DEPLOY_MODE" = "docker" ]; then
        cd "$INSTALL_DIR"
        $compose_cmd exec -T php php artisan up
    else
        cd "$INSTALL_DIR/backend"
        php artisan up
    fi

    # 设置权限
    log_step "修复文件权限..."
    local web_user="www-data"
    [ "$DEPLOY_MODE" = "bt" ] && web_user="www"

    # 所有者
    chown -R "$web_user:$web_user" "$INSTALL_DIR" 2>/dev/null || true

    # 目录 755，文件 644
    find "$INSTALL_DIR" -type d -exec chmod 755 {} \; 2>/dev/null || true
    find "$INSTALL_DIR" -type f -exec chmod 644 {} \; 2>/dev/null || true

    # .env 文件 600（敏感）
    [ -f "$INSTALL_DIR/.env" ] && chmod 600 "$INSTALL_DIR/.env"
    [ -f "$INSTALL_DIR/backend/.env" ] && chmod 600 "$INSTALL_DIR/backend/.env"

    log_success "升级完成！版本: $target_version"
    log_info "备份位置: $backup_path"
}

# 回滚
rollback() {
    log_step "执行回滚"

    local backup_dir="$INSTALL_DIR/storage/backups"
    local latest_backup=$(ls -td "$backup_dir"/*/ 2>/dev/null | head -1)

    if [ -z "$latest_backup" ]; then
        log_error "未找到可用备份"
        exit 1
    fi

    log_info "使用备份: $latest_backup"

    if ! confirm "确认回滚到此备份？"; then
        exit 0
    fi

    # 进入维护模式
    if [ "$DEPLOY_MODE" = "docker" ]; then
        local compose_cmd=$(check_docker_compose)
        cd "$INSTALL_DIR"
        $compose_cmd exec -T php php artisan down || true
    else
        cd "$INSTALL_DIR/backend"
        php artisan down || true
    fi

    # 恢复文件
    log_info "恢复文件..."
    if [ -d "$latest_backup/code/backend" ]; then
        rm -rf "$INSTALL_DIR/backend"
        cp -r "$latest_backup/code/backend" "$INSTALL_DIR/"
    fi

    # 恢复配置
    [ -f "$latest_backup/backend.env" ] && cp "$latest_backup/backend.env" "$INSTALL_DIR/backend/.env"
    [ -f "$latest_backup/config.json" ] && cp "$latest_backup/config.json" "$INSTALL_DIR/"

    # 退出维护模式
    if [ "$DEPLOY_MODE" = "docker" ]; then
        $compose_cmd exec -T php php artisan up
    else
        cd "$INSTALL_DIR/backend"
        php artisan up
    fi

    log_success "回滚完成"
}

# ========================================
# 显示帮助
# ========================================
show_help() {
    cat <<EOF
SSL Manager 在线升级脚本

用法: $0 [选项]

选项:
  --version, -v VERSION  指定升级版本
                         latest   最新稳定版（默认）
                         dev      最新开发版
                         x.x.x    指定版本号
  --file FILE            使用本地升级包
  --dir DIR              指定安装目录（默认自动检测）
  -y, --yes              自动确认，非交互模式
  check                  仅检查更新
  rollback               回滚到上一版本
  -h, --help             显示帮助

环境变量:
  FORCE_CHINA_MIRROR=1   强制使用国内镜像

示例:
  $0                         # 升级到最新稳定版
  $0 --version 1.0.0         # 升级到指定版本
  $0 --file /path/to/pkg.zip # 使用本地包升级
  $0 --dir /www/wwwroot/mysite # 指定安装目录
  $0 rollback                # 回滚

EOF
    exit 0
}

# ========================================
# 显示横幅
# ========================================
show_banner() {
    echo ""
    echo -e "${CYAN}╔═══════════════════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║${NC}           ${GREEN}SSL Manager 在线升级程序${NC}                        ${CYAN}║${NC}"
    echo -e "${CYAN}║${NC}           ${BLUE}https://github.com/$REPO_OWNER/$REPO_NAME${NC}         ${CYAN}║${NC}"
    echo -e "${CYAN}╚═══════════════════════════════════════════════════════════╝${NC}"
    echo ""
}

# ========================================
# 主流程
# ========================================
main() {
    local target_version="latest"
    local upgrade_file=""
    local action="upgrade"

    # 解析参数
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --version|-v)
                target_version="$2"
                shift 2
                ;;
            --file)
                upgrade_file="$2"
                shift 2
                ;;
            --dir)
                INSTALL_DIR="$2"
                shift 2
                ;;
            -y|--yes)
                AUTO_YES=true
                shift
                ;;
            check)
                action="check"
                shift
                ;;
            rollback)
                action="rollback"
                shift
                ;;
            -h|--help)
                show_help
                ;;
            *)
                shift
                ;;
        esac
    done

    show_banner

    # 检查 root 权限
    if [ "$EUID" -ne 0 ]; then
        log_error "请使用 root 权限运行此脚本"
        exit 1
    fi

    # 检测安装目录
    if ! detect_install; then
        log_error "未找到 SSL Manager 安装目录"
        log_info "请确保系统已安装，或使用 install.sh 进行安装"
        exit 1
    fi

    log_info "安装目录: $INSTALL_DIR"
    log_info "部署模式: $DEPLOY_MODE"

    local current_version=$(get_current_version)
    log_info "当前版本: $current_version"

    # 执行动作
    case "$action" in
        check)
            log_info "检查更新功能请在管理后台使用"
            ;;
        rollback)
            rollback
            ;;
        upgrade)
            # 创建临时目录
            mkdir -p "$TEMP_DIR"

            # 如果没有指定本地包，则下载
            if [ -z "$upgrade_file" ]; then
                log_step "下载升级包..."
                upgrade_file="$TEMP_DIR/ssl-manager-full.zip"
                if ! download_upgrade_package "$target_version" "$upgrade_file"; then
                    log_error "无法下载升级包"
                    exit 1
                fi
            fi

            # 验证升级包
            if [ ! -f "$upgrade_file" ]; then
                log_error "升级包不存在: $upgrade_file"
                exit 1
            fi

            # 获取目标版本（如果是 latest，需要从包中读取）
            if [ "$target_version" = "latest" ] || [ "$target_version" = "dev" ]; then
                # 尝试从升级包中读取版本
                local pkg_version=$(unzip -p "$upgrade_file" "*/config.json" 2>/dev/null | grep -o '"version"[[:space:]]*:[[:space:]]*"[^"]*"' | head -1 | cut -d'"' -f4 || echo "")
                if [ -n "$pkg_version" ]; then
                    target_version="$pkg_version"
                fi
            fi

            # 版本检查（禁止降级）
            if [ "$current_version" != "unknown" ] && [ "$target_version" != "latest" ] && [ "$target_version" != "dev" ]; then
                if ! version_gt "$target_version" "$current_version"; then
                    log_error "目标版本 ($target_version) 不高于当前版本 ($current_version)"
                    log_info "不允许降级操作"
                    exit 1
                fi
            fi

            log_info "目标版本: $target_version"
            echo ""

            if [ "$AUTO_YES" != true ]; then
                if ! confirm "确认升级？"; then
                    log_info "已取消升级"
                    exit 0
                fi
            fi

            perform_upgrade "$target_version" "$upgrade_file"
            ;;
    esac
}

# 运行主流程
main "$@"
