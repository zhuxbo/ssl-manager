# ACME 客户端申请 SSL 证书指南

使用 certbot、acme.sh 等标准 ACME 客户端，通过 SSL Manager 的 ACME 服务申请 SSL 证书。

## 前置条件

1. Manager 后端已运行（假设地址为 `http://localhost:5300`）
2. 上级系统已运行，Manager 已配置连接（系统设置 `ca.acmeUrl` + `ca.acmeToken`）
3. 产品表中有 `support_acme = 1` 的 ACME 产品
4. 用户已购买 ACME 产品（拥有有效 Order），或通过 Deploy Token 创建订单

## 第一步：获取 EAB 凭证

ACME 注册需要 EAB（External Account Binding）凭证。EAB 可复用，多次注册不扣费。

### 方式一：Web 页面（推荐）

登录用户端，进入 ACME 订单详情页，EAB 凭据标签页显示 `eab_kid`、`eab_hmac` 和完整的 certbot / acme.sh 注册命令，直接复制使用。

EAB 可复用，密钥丢失后重新获取即可，无需重置。

### 方式二：Deploy Token

适用于自动化部署场景（certbot cron 等）。

**创建 ACME 订单**（首次使用，无现有订单时）：

```bash
curl -s -X POST http://localhost:5300/api/deploy/acme/order \
  -H "Authorization: Bearer <deploy-token>" \
  -H "Content-Type: application/json" \
  -d '{"product_code":"<产品code>","period":<有效期>}' | jq .
```

返回 `order_id`、`eab_kid`、`eab_hmac`、`server_url`。

**获取已有订单的 EAB**：

```bash
curl -s http://localhost:5300/api/deploy/acme/eab/<orderId> \
  -H "Authorization: Bearer <deploy-token>" | jq .
```

返回 `eab_kid`、`eab_hmac`、`server_url`，以及包含账户隔离参数的 `certbot_command`、`acmesh_command`。

## 第二步：注册 ACME 账户

> **账户隔离**：使用独立配置目录，避免多账户/多服务器配置冲突。

### certbot

**Docker 方式（推荐用于测试）**：

```bash
docker run --rm \
  -v certbot-config:/etc/letsencrypt \
  -v certbot-work:/var/lib/letsencrypt \
  -v certbot-logs:/var/log/letsencrypt \
  certbot/certbot register \
  --config-dir /etc/letsencrypt \
  --work-dir /var/lib/letsencrypt \
  --logs-dir /var/log/letsencrypt \
  --server http://host.docker.internal:5300/acme/directory \
  --eab-kid "<eab_kid>" \
  --eab-hmac-key "<eab_hmac>" \
  --email user@example.com \
  --no-eff-email \
  --agree-tos
```

**本机安装方式**：

```bash
certbot register \
  --config-dir ~/acme/certbot \
  --work-dir ~/acme/certbot/work \
  --logs-dir ~/acme/certbot/logs \
  --server http://localhost:5300/acme/directory \
  --eab-kid "<eab_kid>" \
  --eab-hmac-key "<eab_hmac>" \
  --email user@example.com \
  --no-eff-email \
  --agree-tos
```

### acme.sh

```bash
acme.sh --register-account \
  --config-home ~/acme/acmesh \
  --server http://localhost:5300/acme/directory \
  --eab-kid "<eab_kid>" \
  --eab-hmac-key "<eab_hmac>"
```

## 第三步：申请证书

客户端使用 dns-01 挑战方式申请证书。

### certbot

```bash
certbot certonly \
  --config-dir ~/acme/certbot \
  --work-dir ~/acme/certbot/work \
  --logs-dir ~/acme/certbot/logs \
  --server http://localhost:5300/acme/directory \
  --manual --preferred-challenges dns \
  --key-type rsa --rsa-key-size 2048 \
  -d "example.com" \
  -d "*.example.com"
```

### acme.sh

```bash
acme.sh --issue \
  --config-home ~/acme/acmesh \
  --server http://localhost:5300/acme/directory \
  --dns \
  -d "example.com" \
  -d "*.example.com" \
  --yes-I-know-dns-manual-mode-enough-go-ahead-please
```

## 第四步：完成 DNS 验证

如果域名已配置 CNAME 委托，TXT 记录已由 Manager 自动写入，等待 DNS 传播即可。

未配置委托时，certbot 会提示手动添加 DNS TXT 记录：

1. 登录域名 DNS 管理面板
2. 添加 TXT 记录：
   - 主机记录：`_acme-challenge`
   - 记录值：certbot 提示的值
3. 等待 DNS 生效（通常 1-2 分钟）：
   ```bash
   dig TXT _acme-challenge.example.com +short
   ```
4. 确认生效后，在 certbot 中按回车继续

## 第五步：签发证书

所有 DNS 验证通过后，客户端自动提交 CSR 完成签发。

**certbot 证书位置**：

```
/etc/letsencrypt/live/example.com/
├── fullchain.pem   # 证书 + 中间证书
├── privkey.pem     # 私钥
├── cert.pem        # 证书
└── chain.pem       # 中间证书
```

**acme.sh 证书位置**（使用 `--config-home` 时在对应目录下）：

```
~/.acme.sh/example.com_ecc/
├── fullchain.cer   # 证书 + 中间证书
├── example.com.key # 私钥
├── example.com.cer # 证书
└── ca.cer          # 中间证书
```

## 证书续签

续签使用已有的 ACME 账户，不需要重新注册。

### certbot

```bash
# 手动续签
certbot renew \
  --config-dir ~/acme/certbot \
  --work-dir ~/acme/certbot/work \
  --logs-dir ~/acme/certbot/logs

# Docker 方式
docker run --rm \
  -v certbot-config:/etc/letsencrypt \
  -v certbot-work:/var/lib/letsencrypt \
  -v certbot-logs:/var/log/letsencrypt \
  certbot/certbot renew \
  --config-dir /etc/letsencrypt \
  --work-dir /var/lib/letsencrypt \
  --logs-dir /var/log/letsencrypt
```

### acme.sh

```bash
# acme.sh 注册时自动设置 cron 任务续签
# 手动触发
acme.sh --renew \
  --config-home ~/acme/acmesh \
  -d "example.com"
```

### 密钥丢失恢复

如果客户端密钥丢失（如 certbot 数据被清除），EAB 可复用，重新获取 EAB 凭证后注册新账户即可：

1. 通过 Web 页面或 Deploy Token 重新获取 EAB
2. 使用 EAB 重新注册 ACME 账户
3. 重新申请证书

## 证书吊销

### certbot

```bash
certbot revoke \
  --config-dir ~/acme/certbot \
  --server http://localhost:5300/acme/directory \
  --cert-path ~/acme/certbot/live/example.com/cert.pem
```

### acme.sh

```bash
acme.sh --revoke \
  --config-home ~/acme/acmesh \
  -d "example.com" \
  --server http://localhost:5300/acme/directory
```

## 注意事项

- **EAB 可复用**：同一 EAB 凭证可多次注册 ACME 账户，不重复扣费
- **CNAME 委托**：域名配置 `_acme-challenge` CNAME 委托后，系统收到 dns-01 挑战时自动写入 TXT 记录，无需手动添加
- **通配符域名**：必须使用 dns-01 验证方式（`--preferred-challenges dns`）
- **密钥类型**：建议使用 RSA 2048（`--key-type rsa --rsa-key-size 2048`）
- **Docker 网络**：Docker 容器中使用 `host.docker.internal` 访问宿主机服务
