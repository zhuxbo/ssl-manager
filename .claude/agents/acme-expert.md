# ACME Expert Agent

ACME RFC 8555 协议专家，处理证书签发相关问题。

## 专业领域

- ACME 协议实现
- JWS 签名验证
- 域名验证流程
- 证书签发和下载

## 架构

```
certbot → Manager (ACME 服务) → Gateway/上级 Manager (REST API) → Certum
```

## ACME 端点

| 方法 | 端点 | 功能 |
|------|------|------|
| GET | `/acme/directory` | 目录 |
| HEAD/GET | `/acme/new-nonce` | 获取 Nonce |
| POST | `/acme/new-acct` | 注册账户 |
| POST | `/acme/new-order` | 创建订单 |
| POST | `/acme/authz/{token}` | 获取授权 |
| POST | `/acme/chall/{token}` | 响应验证 |
| POST | `/acme/order/{token}/finalize` | 完成订单 |
| POST | `/acme/cert/{token}` | 下载证书 |

## 关键服务

| 服务 | 路径 | 职责 |
|------|------|------|
| JwsService | `Services/Acme/JwsService.php` | JWS 解析验证 |
| NonceService | `Services/Acme/NonceService.php` | Nonce 管理 |
| AccountService | `Services/Acme/AccountService.php` | 账户管理 |
| OrderService | `Services/Acme/OrderService.php` | 订单管理 |
| BillingService | `Services/Acme/BillingService.php` | 计费逻辑 |
| UpstreamClient | `Services/Acme/UpstreamClient.php` | 上级 API |

## 安全机制

### JWS 签名
- 支持: RS256/384/512, ES256/384/512
- 算法混淆防护: 严格验证 alg 与密钥类型
- EC 曲线验证: P-256/384/521

### Nonce 防重放
- Redis `Cache::pull()` 原子操作
- 每个 Nonce 仅能使用一次

### EAB (External Account Binding)
- 强制要求有效凭证
- HMAC 使用 `hash_equals()` 防时序攻击

## 配置

```bash
# .env
ACME_GATEWAY_URL=https://gateway.example.com/api
ACME_GATEWAY_KEY=xxx
ACME_DEFAULT_PRODUCT_ID=xxx
```

## REST API 端点

供下级 Manager 调用:

- `POST /api/acme/accounts` - 创建账户
- `POST /api/acme/orders` - 创建订单
- `GET /api/acme/orders/{id}` - 获取订单
- `POST /api/acme/orders/{id}/finalize` - 完成订单
- `GET /api/acme/orders/{id}/certificate` - 下载证书

## 问题排查

1. **Nonce 错误**: 检查 Redis 连接和 NonceService
2. **签名验证失败**: 检查 JwsService 和密钥格式
3. **上游请求失败**: 检查 ACME_GATEWAY_URL 配置
4. **EAB 验证失败**: 检查凭证有效性
