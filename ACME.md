# ACME 客户端申请 SSL 证书指南

使用 certbot、acme.sh 等标准 ACME 客户端，通过 SSL Manager 的 ACME 服务申请 SSL 证书。

## 架构

```
certbot/acme.sh (ACME 客户端)
    ↓ RFC 8555 协议
Manager (ACME 服务端, /acme/*)
    ↓ REST API
上级 Manager (/api/acme/*)
    ↓
CA (证书颁发机构)
```

## 前置条件

1. Manager 后端已运行（假设地址为 `http://localhost:5300`）
2. 上级系统已运行，Manager 已配置连接（系统设置 `ca.acmeUrl` + `ca.acmeToken`）
3. 产品表中有 `support_acme = 1` 的 ACME 产品
4. 用户已购买 ACME 产品（拥有有效 Order）

## 第一步：获取 EAB 凭证

ACME 注册需要 EAB（External Account Binding）凭证。有三种获取方式：

### 方式一：用户端 Web API（推荐）

登录用户端后通过 API 获取：

```bash
# 查询 EAB 凭证
curl -s http://localhost:5300/api/acme/eab \
  -H "Authorization: Bearer <jwt-token>" | jq .

# 重置 EAB（密钥丢失时使用，不扣费）
curl -s -X POST http://localhost:5300/api/acme/eab/reset \
  -H "Authorization: Bearer <jwt-token>" | jq .
```

**返回示例**：

```json
{
  "code": 1,
  "data": {
    "eab_kid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "eab_hmac": "xYz...base64url...abc",
    "eab_used": false,
    "server_url": "http://localhost:5300/acme/directory",
    "certbot_command": "certbot certonly --server ... --eab-kid ... --eab-hmac-key ...",
    "acmesh_command": "acme.sh --register-account --server ... --eab-kid ... --eab-hmac-key ..."
  }
}
```

> **说明**：EAB 可复用，`eab_hmac` 始终返回。`eab_used` 仅为信息字段。

### 方式二：Deploy Token

适用于自动化部署场景（certbot cron 等）：

```bash
curl -s http://localhost:5300/api/deploy/acme/eab \
  -H "Authorization: Bearer <deploy-token>" | jq .
```

**返回示例**：

```json
{
  "code": 1,
  "data": {
    "eab_kid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "eab_hmac": "xYz...base64url...abc",
    "server_url": "http://localhost:5300/acme/directory"
  }
}
```

> **说明**：EAB 可复用，有效 Order 即可获取 EAB 凭证。

### 方式三：下级 REST API

供下级 Manager 或系统集成调用：

```bash
curl -s -X POST http://localhost:5300/api/acme/accounts \
  -H "Authorization: Bearer <api-token>" \
  -H "Content-Type: application/json" \
  -d '{
    "customer": "user@example.com",
    "product_code": "<产品的 api_id>"
  }' | jq .
```

| 参数 | 说明 |
|------|------|
| `customer` | 用户邮箱（必须是系统中已注册的用户） |
| `product_code` | ACME 产品的 `api_id`（如 `12345`） |

**复用逻辑**（不重复扣费）：

| 场景 | 行为 |
|------|------|
| 无有效 Order | 创建 Order + Cert + EAB，扣费 |
| 有有效 Order | 直接返回已有 EAB（EAB 可复用，不区分已用/未用） |

## 第二步：注册 ACME 账户

### 使用 certbot

**Docker 方式（推荐用于测试）**：

```bash
docker run --rm \
  -v certbot-etc:/etc/letsencrypt \
  -v certbot-var:/var/lib/letsencrypt \
  certbot/certbot register \
  --server http://host.docker.internal:5300/acme/directory \
  --eab-kid "a1b2c3d4-e5f6-7890-abcd-ef1234567890" \
  --eab-hmac-key "xYz...base64url...abc" \
  --email user@example.com \
  --no-eff-email \
  --agree-tos
```

**本机安装方式**：

```bash
certbot register \
  --server http://localhost:5300/acme/directory \
  --eab-kid "a1b2c3d4-e5f6-7890-abcd-ef1234567890" \
  --eab-hmac-key "xYz...base64url...abc" \
  --email user@example.com \
  --no-eff-email \
  --agree-tos
```

### 使用 acme.sh

```bash
acme.sh --register-account \
  --server http://localhost:5300/acme/directory \
  --eab-kid "a1b2c3d4-e5f6-7890-abcd-ef1234567890" \
  --eab-hmac-key "xYz...base64url...abc"
```

**注册时发生了什么**：

1. 客户端生成 RSA/EC 密钥对
2. 发送 JWS 签名请求到 `/acme/new-acct`，携带 EAB 绑定
3. Manager 验证 EAB HMAC 签名 → 首次使用时标记 `eab_used_at`（EAB 可复用）
4. Manager 创建 `AcmeAccount` 记录（关联 `order_id` + JWK 公钥）
5. 返回账户 URL（`/acme/acct/{key_id}`），后续请求用此 URL 标识身份

## 第三步：申请证书

### 方式 A：DNS 手动验证

```bash
certbot certonly \
  --server http://localhost:5300/acme/directory \
  --manual --preferred-challenges dns \
  -d "example.com" \
  -d "*.example.com"
```

### 方式 B：CNAME 委托自动验证（推荐）

如果域名已配置 CNAME 委托（`_acme-challenge.example.com → *.proxy-zone.com`），Manager 会在创建订单时自动写入 DNS TXT 记录，无需手动添加。

```bash
# certbot（使用 --manual + 自动确认脚本）
certbot certonly \
  --server http://localhost:5300/acme/directory \
  --manual --preferred-challenges dns \
  --manual-auth-hook "sleep 5" \
  -d "example.com"

# acme.sh（DNS API 方式同样支持）
acme.sh --issue \
  --server http://localhost:5300/acme/directory \
  --dns dns_cf \
  -d "example.com" \
  -d "*.example.com"
```

**委托自动验证原理**：

1. 用户预先将 `_acme-challenge.example.com` CNAME 到平台代理域名
2. ACME 创建订单时，Manager 自动计算 TXT 值：`base64url(SHA-256(key_authorization))`
3. 通过 `CnameDelegationService` 查找有效委托，调用 `DelegationDnsService` 写入 TXT
4. DNS 传播后，CA 验证时 TXT 记录已就绪
5. 写入为 best-effort：失败不阻塞订单创建，仅记录日志

### 使用 acme.sh（DNS 手动验证）

```bash
acme.sh --issue \
  --server http://localhost:5300/acme/directory \
  --dns \
  -d "example.com" \
  --yes-I-know-dns-manual-mode-enough-go-ahead-please
```

**申请时发生了什么**：

1. 客户端发送 JWS 签名请求到 `/acme/new-order`，携带域名列表
2. Manager 通过 `AcmeAccount.order_id` 精确定位 Order（兼容旧数据回落 `user_id` 查询）
3. SAN 验证（域名数量不超产品限制）+ 幂等扣费（SAN 增购）
4. 调上级 REST API 创建订单，存储 ID 映射（`cert.api_id`）
5. 创建 `AcmeAuthorization` 记录，存储 `key_authorization`
6. **自动尝试委托写 TXT**：遍历 dns-01 授权，查找有效委托并写入 TXT
7. 返回 DNS 验证挑战信息给客户端

## 第四步：完成 DNS 验证

如果使用了 CNAME 委托自动验证，TXT 记录已自动写入，等待 DNS 传播即可。

手动验证时，certbot 会提示添加 DNS TXT 记录：

```
Please deploy a DNS TXT record under the name:

  _acme-challenge.example.com

with the following value:

  gfj9Xq...Rg85nM

Before continuing, verify the TXT record has been deployed.
```

**操作步骤**：

1. 登录域名 DNS 管理面板
2. 添加 TXT 记录：
   - 主机记录：`_acme-challenge`
   - 记录值：certbot 提示的值
3. 等待 DNS 生效（通常 1-2 分钟，可用 `dig` 命令验证）：
   ```bash
   dig TXT _acme-challenge.example.com +short
   ```
4. 确认生效后，在 certbot 中按回车继续

**验证时发生了什么**：

1. 客户端发送请求到 `/acme/chall/{token}`
2. Manager 通过 `OrderService.respondToChallenge()` 转发到上级
3. 上级系统/CA 查询 DNS TXT 记录进行验证
4. 验证通过 → 授权状态更新为 `valid`
5. 所有授权通过后，订单状态变为 `ready`

## 第五步：签发证书

所有 DNS 验证通过后，客户端自动提交 CSR 完成签发：

1. 客户端生成 CSR，发送到 `/acme/order/{referId}/finalize`
2. Manager 转发 CSR 到上级 → CA 签发证书
3. 客户端从 `/acme/cert/{referId}` 下载证书 + 中间证书
4. 证书保存到本地

**certbot 证书位置**：

```
/etc/letsencrypt/live/example.com/
├── fullchain.pem   # 证书 + 中间证书
├── privkey.pem     # 私钥
├── cert.pem        # 证书
└── chain.pem       # 中间证书
```

**acme.sh 证书位置**：

```
~/.acme.sh/example.com_ecc/
├── fullchain.cer   # 证书 + 中间证书
├── example.com.key # 私钥
├── example.com.cer # 证书
└── ca.cer          # 中间证书
```

## 证书续签

### certbot 续签

```bash
# 手动续签
certbot renew --server http://localhost:5300/acme/directory

# Docker 方式
docker run --rm \
  -v certbot-etc:/etc/letsencrypt \
  -v certbot-var:/var/lib/letsencrypt \
  certbot/certbot renew \
  --server http://host.docker.internal:5300/acme/directory
```

### acme.sh 续签

```bash
# acme.sh 会自动设置 cron 任务续签
# 手动触发
acme.sh --renew -d "example.com" --server http://localhost:5300/acme/directory
```

**续签流程说明**：

- 续签使用已有的 ACME 账户（JWS 密钥），不需要重新注册
- certbot/acme.sh 直接发起 `new-order`，Manager 在已有 Order 上创建新 Cert
- 如果 Order 过期且开启了 `auto_renew`，`BillingService` 会自动续费创建新 Order，并迁移 `AcmeAccount.order_id` 到新 Order
- 续费时自动通知上游创建新订单（best-effort），确保后续 ACME 操作可用
- 已配置 CNAME 委托的域名会自动完成 DNS 验证

### JWS 密钥丢失恢复

如果客户端密钥丢失（如 certbot 数据被清除）：

1. 通过用户端 API 重置 EAB：`POST /api/acme/eab/reset`
2. 使用新 EAB 重新注册 ACME 账户
3. `createAccount` 复用已有 Order，不重复扣费

## 证书吊销

### certbot 吊销

```bash
certbot revoke \
  --server http://localhost:5300/acme/directory \
  --cert-path /etc/letsencrypt/live/example.com/cert.pem
```

### acme.sh 吊销

```bash
acme.sh --revoke -d "example.com" \
  --server http://localhost:5300/acme/directory
```

## 完整数据流

```
第一步  获取 EAB
        ├─ 用户端 API: GET /api/acme/eab（查询）/ POST /api/acme/eab/reset（重置）
        ├─ Deploy Token: GET /api/deploy/acme/eab
        └─ REST API: POST /api/acme/accounts（下级调用，自动复用 Order 不重复扣费）
        → 返回 eab_kid + eab_hmac + server_url

第二步  ACME: POST /acme/new-acct (JWS + EAB)
        → 验证 EAB HMAC 签名 → 标记 eab_used_at
        → 创建 AcmeAccount（order_id + JWK 公钥）
        → 如有上级：调上级 createAccount → 存 order.acme_account_id

第三步  ACME: POST /acme/new-order (JWS + KID)
        → 通过 AcmeAccount.order_id 精确定位 Order
        → SAN 验证 + 幂等扣费
        → 调上级 createOrder（使用 order.acme_account_id + product.api_id）
        → 存映射 cert.api_id，创建 AcmeAuthorization
        → 自动尝试委托写 TXT（best-effort）

第四步  ACME: POST /acme/chall/{token} (JWS + KID)
        → 调上级 respondToChallenge（使用 authorization.acme_challenge_id）
        → 上级/CA 验证 DNS → 更新授权状态

第五步  ACME: POST /acme/order/{referId}/finalize (JWS + KID)
        → 调上级 finalizeOrder（使用 cert.api_id）
        → CA 签发 → 调上级 getCertificate → 存储证书

第六步  ACME: POST /acme/cert/{referId} (JWS + KID)
        → 返回证书 + 中间证书
```

## 数据模型关联

```
AcmeAccount
  ├── user_id          → 用户
  ├── order_id         → Order（精确关联，续费时自动迁移）
  ├── acme_account_id  → 上级系统账户 ID
  └── key_id           → JWK 公钥指纹

Order (support_acme=1)
  ├── eab_kid / eab_hmac / eab_used_at  → EAB 凭证
  ├── acme_account_id                    → 上级系统账户 ID
  └── latestCert → Cert (channel=acme)
                     ├── api_id            → 上级系统订单 ID
                     └── acmeAuthorizations
                          ├── acme_challenge_id  → 上级系统 challenge ID
                          └── key_authorization  → CA 验证值
```

## 注意事项

- **EAB 可复用**：同一 EAB 凭证可多次注册 ACME 账户（Certum 订单级认证模式），`eab_used_at` 仅记录首次使用时间
- **密钥丢失不扣费**：通过 `POST /api/acme/eab/reset` 或 `createAccount` 复用逻辑重新获取 EAB
- **CNAME 委托加速**：配置委托后，ACME 申请自动写 TXT，无需手动添加 DNS 记录
- **DNS 生效时间**：手动添加 TXT 记录后建议等待 1-2 分钟再继续验证
- **通配符域名**：必须使用 DNS 验证方式（`--preferred-challenges dns`），委托写 TXT 时自动去除 `*.` 前缀
- **续费联动**：Order 自动续费后，`AcmeAccount.order_id` 自动迁移到新 Order
- **Docker 网络**：Docker 容器中使用 `host.docker.internal` 访问宿主机服务
- **ACME 认证**：客户端请求通过 JWS 签名认证（RSA/EC），支持 RS256/384/512、ES256/384/512
- **Nonce 防重放**：每个请求携带一次性 Nonce，Manager 通过 Redis 存储和原子消费
