#!/usr/bin/env bash
#
# ACME E2E 完整流程测试
# 流程: 环境检查 → 注册 → 申请证书 → 验证签发 → 吊销 → 取消/扣费退款指引
#
# 域名需已配置委托（CNAME 委托），Manager 在 new-order 时自动写 TXT 记录
# certbot 使用 --manual-auth-hook "sleep 30" 等待 DNS 传播
#

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
VOLUME_ETC="certbot-e2e-etc"
VOLUME_VAR="certbot-e2e-var"
DEFAULT_SERVER="http://host.docker.internal:5300/acme/directory"
DEFAULT_EMAIL="test@example.com"

# 颜色
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

step_num=0
step() {
    ((step_num++))
    echo ""
    echo -e "${CYAN}=== 步骤 $step_num: $1 ===${NC}"
    echo ""
}

ok() {
    echo -e "${GREEN}✓ $1${NC}"
}

warn() {
    echo -e "${YELLOW}! $1${NC}"
}

fail() {
    echo -e "${RED}✗ $1${NC}"
    exit 1
}

sql_hint() {
    echo ""
    echo -e "${YELLOW}--- 数据库验证 SQL ---${NC}"
    echo "$1"
    echo -e "${YELLOW}---------------------${NC}"
}

# --- 参数解析 ---
eab_kid=""
eab_hmac=""
domain=""
email="$DEFAULT_EMAIL"
server="$DEFAULT_SERVER"
do_clean=false

usage() {
    cat <<EOF
用法: $0 [选项]

必填参数:
  --eab-kid <kid>       EAB Key ID
  --eab-hmac <hmac>     EAB HMAC Key
  --domain <domain>     测试域名（已配置委托的域名）

可选参数:
  --email <email>       注册邮箱（默认: $DEFAULT_EMAIL）
  --server <url>        ACME server URL（默认: $DEFAULT_SERVER）
  --clean               清理 certbot volumes 后退出

示例:
  $0 --eab-kid "abc123" --eab-hmac "def456" --domain "test.example.com"
  $0 --clean
EOF
    exit 1
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --eab-kid)   eab_kid="$2"; shift 2 ;;
        --eab-hmac)  eab_hmac="$2"; shift 2 ;;
        --domain)    domain="$2"; shift 2 ;;
        --email)     email="$2"; shift 2 ;;
        --server)    server="$2"; shift 2 ;;
        --clean)     do_clean=true; shift ;;
        -h|--help)   usage ;;
        *)           echo "未知参数: $1"; usage ;;
    esac
done

# --- 清理模式 ---
if [ "$do_clean" = true ]; then
    echo "=== 清理 certbot E2E 数据 ==="
    docker volume rm "$VOLUME_ETC" "$VOLUME_VAR" 2>/dev/null && echo "已删除 volumes" || echo "volumes 不存在，无需清理"
    exit 0
fi

# --- 参数校验 ---
if [ -z "$eab_kid" ] || [ -z "$eab_hmac" ] || [ -z "$domain" ]; then
    echo "错误: --eab-kid, --eab-hmac, --domain 为必填参数"
    echo ""
    usage
fi

echo "========================================"
echo "  ACME E2E 完整流程测试"
echo "========================================"
echo ""
echo "  Server:  $server"
echo "  Domain:  $domain"
echo "  Email:   $email"
echo "  EAB KID: $eab_kid"

# ============================================================
# 步骤 1: 环境检查
# ============================================================
step "环境检查"
bash "$SCRIPT_DIR/check-backend.sh"
ok "环境检查通过"

# ============================================================
# 步骤 2: certbot 注册
# ============================================================
step "certbot 注册账户（EAB）"

docker run --rm \
    -v "$VOLUME_ETC":/etc/letsencrypt \
    -v "$VOLUME_VAR":/var/lib/letsencrypt \
    certbot/certbot register \
    --server "$server" \
    --eab-kid "$eab_kid" \
    --eab-hmac-key "$eab_hmac" \
    --email "$email" \
    --no-eff-email \
    --agree-tos

ok "账户注册成功"

sql_hint "-- Manager: 检查 ACME 账户创建
SELECT id, order_id, kid, status, created_at
FROM acme_accounts
ORDER BY id DESC LIMIT 5;"

# ============================================================
# 步骤 3: certbot 申请证书（委托自动验证）
# ============================================================
step "certbot 申请证书（委托自动验证）"

echo "域名: $domain"
echo "验证方式: DNS 委托自动验证（Manager 自动写 TXT）"
echo "等待 DNS 传播: sleep 30"
echo ""

docker run --rm \
    -v "$VOLUME_ETC":/etc/letsencrypt \
    -v "$VOLUME_VAR":/var/lib/letsencrypt \
    certbot/certbot certonly \
    --server "$server" \
    --manual --preferred-challenges dns \
    --manual-auth-hook "sleep 30" \
    --key-type rsa \
    -d "$domain"

ok "证书申请成功"

sql_hint "-- Manager: 检查证书签发 + 扣费
SELECT c.id, c.domain, c.status, c.channel, c.amount, c.created_at
FROM certs c
WHERE c.channel = 'acme'
ORDER BY c.id DESC LIMIT 5;

-- Manager: 检查扣费交易
SELECT t.id, t.type, t.amount, t.balance_before, t.balance_after, t.created_at
FROM transactions t
ORDER BY t.id DESC LIMIT 5;

-- Manager: 检查用户余额
SELECT id, email, balance FROM users WHERE email = '$email';"

# ============================================================
# 步骤 4: 验证证书签发
# ============================================================
step "验证证书签发结果"

docker run --rm \
    -v "$VOLUME_ETC":/etc/letsencrypt \
    certbot/certbot certificates

ok "证书验证完成"

sql_hint "-- Manager: 检查 ACME 授权状态
SELECT aa.id, aa.domain, aa.status, aa.type, aa.created_at
FROM acme_authorizations aa
ORDER BY aa.id DESC LIMIT 5;"

# ============================================================
# 步骤 5: 吊销证书
# ============================================================
step "吊销证书"

# certbot 存储证书时去掉通配符前缀（*.example.com → example.com）
cert_name="${domain#\*.}"

docker run --rm \
    -v "$VOLUME_ETC":/etc/letsencrypt \
    -v "$VOLUME_VAR":/var/lib/letsencrypt \
    certbot/certbot revoke \
    --server "$server" \
    --cert-path "/etc/letsencrypt/live/$cert_name/cert.pem" \
    --non-interactive

ok "证书吊销成功"

sql_hint "-- Manager: 检查证书状态变为 revoked
SELECT c.id, c.domain, c.status, c.channel, c.created_at
FROM certs c
WHERE c.channel = 'acme'
ORDER BY c.id DESC LIMIT 5;"

# ============================================================
# 步骤 6: 取消订单 + 扣费退款验证指引
# ============================================================
step "取消订单 + 扣费退款验证（手动操作）"

echo "证书已签发并吊销，以下为取消订单的测试指引。"
echo "需要通过 Manager 后台或 API 手动执行取消操作。"
echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo -e "${YELLOW}场景 A: pending 状态取消（未扣费）${NC}"
echo "  - 创建新订阅但不提交 new-order（不触发扣费）"
echo "  - 执行取消：DELETE /api/admin/orders/{id}"
echo "  - 预期：快速清理，无退费"
echo ""
sql_hint "-- 验证 pending 取消: 无交易记录
SELECT * FROM transactions WHERE order_id = <ORDER_ID>;"

echo ""
echo -e "${YELLOW}场景 B: processing / approving 状态取消（已扣费）${NC}"
echo "  - 证书正在签发过程中执行取消"
echo "  - 执行取消：DELETE /api/admin/orders/{id}"
echo "  - 预期：创建 cancelling 延迟任务，通知上游取消，2 分钟后退费"
echo ""
sql_hint "-- 验证扣费 + 退费
-- 1. 扣费记录（type=order）
SELECT id, type, amount, balance_before, balance_after
FROM transactions
WHERE order_id = <ORDER_ID> AND type = 'order';

-- 2. 退费记录（type=cancel，金额应等于扣费金额）
SELECT id, type, amount, balance_before, balance_after
FROM transactions
WHERE order_id = <ORDER_ID> AND type = 'cancel';

-- 3. 用户余额恢复
SELECT id, email, balance FROM users WHERE id = <USER_ID>;"

echo ""
echo -e "${YELLOW}场景 C: active 状态取消（退费周期内）${NC}"
echo "  - 证书已签发且在退费周期内"
echo "  - 执行取消：DELETE /api/admin/orders/{id}"
echo "  - 预期：通知上游取消 + 退费，同场景 B"
echo ""

echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

# ============================================================
# 完成
# ============================================================
echo ""
echo "========================================"
echo -e "  ${GREEN}E2E 自动化流程完成${NC}"
echo "========================================"
echo ""
echo "已完成: 环境检查 → 注册 → 申请证书 → 验证签发 → 吊销"
echo "待手动: 各状态取消测试（见上方指引）"
echo ""
echo "后续操作："
echo "  查看证书:  docker run --rm -v $VOLUME_ETC:/etc/letsencrypt certbot/certbot certificates"
echo "  清理数据:  bash $SCRIPT_DIR/run-e2e.sh --clean"
