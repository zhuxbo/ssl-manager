# ACME 端到端测试

本地 Docker 环境下 ACME 全链路端到端测试方案。

## 架构

```
certbot (Docker)
    ↓ RFC 8555
Manager backend (:5300)
    ↓ REST API (/api/acme/*)
Gateway backend (:6300)
    ↓ Certum REST API v0.2
Certum CA
```

## 正式运行前配置检查

运行 E2E 测试前，确认以下配置已就绪：

### Gateway 配置

`system_settings` 表 `group='ca'` 需包含 Certum CA 信息：

| key | 说明 |
|-----|------|
| `certumRestUrl` | Certum REST API 地址 |
| `certumOauthUrl` | Certum OAuth 地址 |
| `certumClientId` | OAuth 客户端 ID |
| `certumUsername` | OAuth 用户名 |
| `certumPassword` | OAuth 密码 |
| `certumAcmeDirectory` | Certum ACME 目录地址 |

Gateway `users` 表需有 API 用户，其 `api_token` 供 Manager 调用。
`products` 表需有 `support_acme = 1` 且 `status = 1` 的 ACME 产品。

### Manager 配置

`system_settings` 表 `group='ca'` 需包含：

| key | 说明 | 值 |
|-----|------|----|
| `acmeUrl` | Gateway ACME API 地址 | `http://gateway-backend:8000/api/acme` |
| `acmeToken` | Gateway 用户的 api_token | 从 Gateway users 表获取 |

Manager 还需要：
- `products` 表有 `support_acme = 1` 的 ACME 产品
- 有注册用户和有效 Order（用于获取 EAB）

### 域名委托配置

测试域名需已配置 DNS 委托：
- `_acme-challenge.<域名>` CNAME 指向 Manager 代理域名
- Manager 数据库中有对应的委托记录

需要提供：**已配置委托的域名**（用于 `--domain` 参数）

## 自动化脚本

### check-backend.sh — 后端环境检查

检查 E2E 测试所需的后端服务是否就绪。

**检查项**：
1. Docker 可用
2. Manager :5300 端口可达
3. ACME directory 有效（包含 newAccount / newOrder）
4. Gateway :6300 端口可达

任一检查失败则终止并给出排查建议。

```bash
# 默认检查（Manager :5300, Gateway :6300）
bash manager/skills/acme-e2e-test/check-backend.sh

# 自定义端口
MANAGER_URL=http://localhost:5301 GATEWAY_URL=http://localhost:6301 \
  bash manager/skills/acme-e2e-test/check-backend.sh
```

### run-e2e.sh — 完整 E2E 流程

自动执行：环境检查 → 注册 → 申请证书 → 验证签发 → 吊销，并打印扣费/退款验证 SQL 和取消测试指引。

**参数**：

| 参数 | 必填 | 说明 |
|------|------|------|
| `--eab-kid` | 是 | EAB Key ID |
| `--eab-hmac` | 是 | EAB HMAC Key |
| `--domain` | 是 | 测试域名（已配置委托的域名） |
| `--email` | 否 | 注册邮箱，默认 test@example.com |
| `--server` | 否 | ACME server，默认 `http://host.docker.internal:5300/acme/directory` |
| `--clean` | 否 | 清理 certbot volumes 后退出 |

**使用示例**：

```bash
# 完整 E2E 流程
bash manager/skills/acme-e2e-test/run-e2e.sh \
  --eab-kid "abc123" --eab-hmac "def456" \
  --domain "test.example.com"

# 自定义邮箱和 server
bash manager/skills/acme-e2e-test/run-e2e.sh \
  --eab-kid "abc123" --eab-hmac "def456" \
  --domain "test.example.com" \
  --email "admin@example.com" \
  --server "http://host.docker.internal:5301/acme/directory"

# 清理 certbot volumes
bash manager/skills/acme-e2e-test/run-e2e.sh --clean
```

**自动化流程**：
1. 调用 `check-backend.sh` 检查环境
2. `certbot register` — EAB 注册
3. `certbot certonly` — 委托自动验证（`--manual-auth-hook "sleep 30"` 等待 DNS 传播）
4. `certbot certificates` — 验证证书签发
5. `certbot revoke` — 吊销证书
6. 打印取消订单操作指引（手动步骤）

每个关键步骤后打印数据库验证 SQL，供人工在数据库中执行检查。

### 扣费/退款验证 SQL 参考

```sql
-- 扣费记录
SELECT id, type, amount, balance_before, balance_after, created_at
FROM transactions
WHERE order_id = <ORDER_ID> AND type = 'order';

-- 退款记录（取消后）
SELECT id, type, amount, balance_before, balance_after, created_at
FROM transactions
WHERE order_id = <ORDER_ID> AND type = 'cancel';

-- 用户余额变化
SELECT id, email, balance FROM users WHERE id = <USER_ID>;

-- ACME 账户状态
SELECT id, order_id, kid, status, created_at
FROM acme_accounts ORDER BY id DESC LIMIT 5;

-- 证书状态
SELECT id, domain, status, channel, amount, created_at
FROM certs WHERE channel = 'acme' ORDER BY id DESC LIMIT 5;
```

### 取消场景操作指引

脚本完成签发/吊销后，需手动通过 Manager API 测试取消：

| 场景 | 操作 | 预期 |
|------|------|------|
| pending 取消 | 创建订阅但不提交 new-order，执行 `DELETE /api/admin/orders/{id}` | 快速清理，无退费 |
| processing 取消 | 证书签发过程中执行取消 | 创建 cancelling 延迟任务，通知上游取消，2 分钟后退费 |
| active 取消 | 证书已签发且在退费周期内 | 通知上游取消 + 退费，同 processing |

## 前置条件

### 1. Docker 网络

两个服务共享 `cnssl-dev-network`：

```bash
docker network create cnssl-dev-network
```

### 2. Gateway 环境

```bash
cd gateway/backend/develop/docker

# 配置端口（.env）
BACKEND_PORT=6300
MYSQL_PORT=3307
REDIS_PORT=6380

# 首次初始化
./start.sh init

# 启动
./start.sh up
```

### 3. Manager 环境

```bash
cd manager/develop

# 配置端口（.env）
BACKEND_PORT=5300
MYSQL_PORT=3306
REDIS_PORT=6379

# 首次初始化
./start.sh init

# 启动
./start.sh up
```

> **网络说明**：Manager 容器通过 Docker 网络访问 Gateway 容器，使用容器名 `gateway-backend` + 内部端口 `8000`。也可用 `http://host.docker.internal:6300/api/acme` 通过宿主机端口访问。

## 手动测试流程

### 步骤 1：获取 EAB 凭证

三种方式任选其一：

**方式 A — REST API（下级系统调用）**：

```bash
curl -s -X POST http://localhost:5300/api/acme/accounts \
  -H "Authorization: Bearer <manager-api-token>" \
  -H "Content-Type: application/json" \
  -d '{"customer":"user@example.com","product_code":"<product-api-id>"}' | jq .
```

**方式 B — 用户端 API**：

```bash
curl -s http://localhost:5300/api/acme/eab \
  -H "Authorization: Bearer <jwt-token>" | jq .
```

**方式 C — Deploy Token**：

```bash
curl -s http://localhost:5300/api/deploy/acme/eab \
  -H "Authorization: Bearer <deploy-token>" | jq .
```

返回值包含 `eab_kid`、`eab_hmac`、`server_url`。

### 步骤 2：certbot 注册

```bash
docker run --rm \
  -v certbot-etc:/etc/letsencrypt \
  -v certbot-var:/var/lib/letsencrypt \
  certbot/certbot register \
  --server http://host.docker.internal:5300/acme/directory \
  --eab-kid "<eab_kid>" \
  --eab-hmac-key "<eab_hmac>" \
  --email user@example.com \
  --no-eff-email \
  --agree-tos
```

### 步骤 3：申请证书

**DNS 手动验证**：

```bash
docker run --rm -it \
  -v certbot-etc:/etc/letsencrypt \
  -v certbot-var:/var/lib/letsencrypt \
  certbot/certbot certonly \
  --server http://host.docker.internal:5300/acme/directory \
  --manual --preferred-challenges dns \
  -d "example.com"
```

certbot 会提示添加 DNS TXT 记录 `_acme-challenge.example.com`，添加后按回车继续。

**CNAME 委托自动验证**（域名已配置委托时，Manager 自动写 TXT）：

```bash
docker run --rm \
  -v certbot-etc:/etc/letsencrypt \
  -v certbot-var:/var/lib/letsencrypt \
  certbot/certbot certonly \
  --server http://host.docker.internal:5300/acme/directory \
  --manual --preferred-challenges dns \
  --manual-auth-hook "sleep 30" \
  -d "example.com"
```

### 步骤 4：验证结果

```bash
docker run --rm \
  -v certbot-etc:/etc/letsencrypt \
  certbot/certbot certificates
```

### 步骤 5：续签测试（可选）

```bash
docker run --rm \
  -v certbot-etc:/etc/letsencrypt \
  -v certbot-var:/var/lib/letsencrypt \
  certbot/certbot renew \
  --server http://host.docker.internal:5300/acme/directory
```

### 步骤 6：吊销测试（可选）

```bash
docker run --rm \
  -v certbot-etc:/etc/letsencrypt \
  -v certbot-var:/var/lib/letsencrypt \
  certbot/certbot revoke \
  --server http://host.docker.internal:5300/acme/directory \
  --cert-path /etc/letsencrypt/live/example.com/cert.pem
```

## 清理

```bash
# 清理 certbot 数据（重新测试前）
docker volume rm certbot-e2e-etc certbot-e2e-var

# 或通过脚本清理
bash manager/skills/acme-e2e-test/run-e2e.sh --clean

# 停止服务
cd manager/develop && ./start.sh down
cd gateway/backend/develop/docker && ./start.sh down
```

## 常见问题

| 问题 | 排查 |
|------|------|
| Manager 连不上 Gateway | 确认同一 Docker 网络，检查 `ca.acmeUrl`/`ca.acmeToken` |
| certbot 连不上 Manager | Docker 中用 `host.docker.internal` 替代 `localhost` |
| Gateway Certum OAuth 失败 | 检查 system_settings 中 certumUsername/certumPassword，清 Redis 缓存 |
| EAB 获取失败 | 确认有 `support_acme=1` 的产品和有效 Order |
| `encrypted` cast 报错 | APP_KEY 变更后需清空 `acme_ca_accounts` 重建 |
| 验证超时 | DNS TXT 记录传播需 1-2 分钟，`dig TXT _acme-challenge.xxx +short` 确认 |
| certbot finalize 报 badCSR / Unsupported key algorithm | Certum 测试环境不支持 ECDSA 密钥，certbot 需指定 `--key-type rsa --rsa-key-size 2048` |

## 关键配置参考

### Manager AcmeApiClient 配置优先级

1. `system_settings('ca', 'acmeUrl')` → 直接使用
2. 回落：`system_settings('ca', 'url')` 路径替换为 `/api/acme`

Token: `system_settings('ca', 'acmeToken')` → 回落 `system_settings('ca', 'token')`

### Gateway ACME API 认证

`api.v2` 中间件 → `ApiAuthenticate` → 通过 `Authorization: Bearer <api_token>` 匹配 users 表。

### 端口映射

| 服务 | 宿主机端口 | 容器内端口 | 容器名 |
|------|-----------|-----------|--------|
| Manager backend | 5300 | 8000 | manager-backend |
| Gateway backend | 6300 | 8000 | gateway-backend |
| Manager MySQL | 3306 | 3306 | manager-mysql |
| Gateway MySQL | 3307 | 3306 | gateway-mysql |
| Gateway Redis | 6380 | 6379 | gateway-redis |
