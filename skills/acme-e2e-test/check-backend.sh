#!/usr/bin/env bash
#
# ACME E2E 后端检查脚本
# 检查 Manager、Gateway、ACME directory、Docker 是否就绪
# 任一检查失败则终止并给出排查建议
#

set -euo pipefail

MANAGER_URL="${MANAGER_URL:-http://localhost:5300}"
GATEWAY_URL="${GATEWAY_URL:-http://localhost:6300}"
CURL_TIMEOUT=5

has_failure=false

check_pass() {
    echo "  [OK]   $1"
}

check_fail() {
    echo "  [FAIL] $1"
    echo "         → $2"
    has_failure=true
}

echo "=== ACME E2E 后端检查 ==="
echo ""
echo "Manager: $MANAGER_URL"
echo "Gateway: $GATEWAY_URL"
echo ""

# --- 检查 1: Docker 可用 ---
if command -v docker &>/dev/null && docker info &>/dev/null 2>&1; then
    check_pass "Docker 可用"
else
    check_fail "Docker 可用" "Docker 未安装或未运行，certbot 需要 Docker 环境"
fi

# --- 检查 2: Manager 端口可达 ---
directory_response=""
if directory_response=$(curl -sf --max-time "$CURL_TIMEOUT" "$MANAGER_URL/acme/directory" 2>&1); then
    check_pass "Manager $MANAGER_URL 可达"
else
    check_fail "Manager $MANAGER_URL 可达" "无法连接，确认 Manager 已启动且端口正确（docker ps | grep manager）"
fi

# --- 检查 3: ACME directory 有效 ---
if [ -n "$directory_response" ]; then
    has_new_account=$(echo "$directory_response" | grep -c '"newAccount"' 2>/dev/null || true)
    has_new_order=$(echo "$directory_response" | grep -c '"newOrder"' 2>/dev/null || true)

    if [ "$has_new_account" -gt 0 ] && [ "$has_new_order" -gt 0 ]; then
        check_pass "ACME directory 包含 newAccount / newOrder"
    else
        check_fail "ACME directory 包含 newAccount / newOrder" "directory 响应缺少必要字段，检查 Manager system_settings 表 group='ca' 的 acmeUrl 配置"
    fi
else
    check_fail "ACME directory 有效" "跳过（Manager 不可达）"
fi

# --- 检查 4: Gateway 端口可达 ---
gateway_status=$(curl -s -o /dev/null -w "%{http_code}" --max-time "$CURL_TIMEOUT" "$GATEWAY_URL/api/acme/orders" 2>&1) || gateway_status="000"
if [ "$gateway_status" != "000" ]; then
    check_pass "Gateway $GATEWAY_URL 可达 (HTTP ${gateway_status})"
else
    check_fail "Gateway $GATEWAY_URL 可达" "无法连接，确认 Gateway 已启动且端口正确（docker ps | grep gateway）"
fi

# --- 汇总 ---
echo ""

if [ "$has_failure" = true ]; then
    echo "--- 检查未通过，请修复后重试 ---"
    echo ""
    echo "排查建议："
    echo "  1. 确认容器运行中：docker ps"
    echo "  2. Manager 配置：system_settings 表 group='ca' 的 acmeUrl / acmeToken"
    echo "  3. Gateway 配置：system_settings 表 group='ca' 的 Certum CA 信息"
    echo "  4. Docker 网络：docker network inspect cnssl-dev-network"
    echo "  5. 查看日志：docker logs manager-backend / docker logs gateway-backend"
    exit 1
fi

echo "--- 全部检查通过 ---"
