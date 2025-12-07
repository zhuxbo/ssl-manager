# SSL 证书管理系统 - 后端 API

基于 Laravel 11.x 构建的 SSL 证书管理系统后端 API，采用纯 API 架构，支持多 CA 品牌证书申请、管理和自动化处理。

## 项目特点

- 🔐 **纯 API 架构** - 无 session/cookie 依赖，完全前后端分离
- 🎯 **多端认证** - 支持用户端、管理端 JWT 认证和 API Token 认证
- 🏢 **多 CA 支持** - 集成 GoGetSSL、Racent 等多个 CA 品牌
- 💰 **完整财务** - 订单、交易、发票、资金管理一体化
- 🔄 **异步处理** - Redis 队列支持，任务状态实时跟踪
- 📊 **全面日志** - API、用户、管理员、CA、错误等分类日志
- 🐳 **部署简单** - 脚本一键部署

## 技术栈

- **框架**: Laravel 11.x
- **PHP 版本**: 8.3+
- **数据库**: MySQL 8.0
- **缓存/队列**: Redis
- **认证**: JWT (tymon/jwt-auth)
- **测试**: PHPUnit + Pest
- **代码质量**: PHPStan + PHP Pint
- **第三方集成**:
  - 支付: 支付宝、微信支付 (yansongda/pay)
  - 通信: 短信 (overtrue/easy-sms)、邮件 (phpmailer/phpmailer)
  - 文档: Excel 处理 (phpoffice/phpspreadsheet)

## 快速开始

### 环境要求

- PHP 8.3+
- MySQL 8.0+
- Redis
- Composer
- JRE 17+ (可选，用于 keytool 生成 JKS 证书)

### 安装步骤

1. **克隆项目**

    ```bash
    git clone <repository-url>
    cd backend
    ```

2. **安装依赖**

    ```bash
    composer install
    ```

3. **环境配置**

    ```bash
    cp .env.example .env
    # 编辑 .env 文件配置数据库、Redis等信息
    ```

4. **生成密钥**

    ```bash
    php artisan key:generate
    php artisan jwt:secret
    ```

5. **数据库迁移**

    ```bash
    php artisan migrate
    php artisan db:seed
    ```

6. **启动服务**

    ```bash
    php artisan serve
    ```

详细安装说明请参考 [INSTALL.md](INSTALL.md)

IDE 代码提示辅助工具：
Phpstorm Laravel Idea 插件优先
barryvdh/laravel-ide-helper 包用于其它 IDE

### 认证方式

- **用户端 API**: JWT 认证，路径前缀 `/api/`
- **管理端 API**: JWT 认证，路径前缀 `/api/admin/`
- **API v1/v2**: Token 认证，路径前缀 `/api/V1/`, `/api/v2/`

### 响应格式

```json
{
  "code": 1,
  "data": {
  }
}
```

```json
{
  "code": 0,
  "msg": "错误信息",
  "errors": {
  }
}
```

### 主要 API 端点

- **认证相关**: 登录、注册、刷新令牌
- **证书管理**: 申请、续费、重新颁发、同步状态
- **订单管理**: 创建、支付、提交、批量操作
- **财务管理**: 交易记录、资金流水、发票管理
- **系统管理**: 用户管理、产品管理、设置配置

## 项目结构

```txt
app/
├── Http/Controllers/     # API控制器
│   ├── User/            # 用户端控制器
│   ├── Admin/           # 管理端控制器
│   ├── V1/              # API v1版本
│   ├── V2/              # API v2版本
│   └── Callback/        # 回调处理
├── Models/              # 数据模型
├── Services/            # 业务逻辑层
│   └── Order/    # 订单服务核心
├── Http/Requests/       # 表单验证
├── Exceptions/          # 异常处理
├── Traits/              # 通用特性
├── Utils/               # 工具类
├── Jobs/                # 队列任务
└── Bootstrap/           # 启动配置

routes/                  # 路由定义
├── api.user.php        # 用户端路由
├── api.admin.php       # 管理端路由
├── api.v1.php          # API v1路由
├── api.v2.php          # API v2路由
└── api.callback.php    # 回调路由

database/
├── migrations/         # 数据库迁移
└── seeders/           # 数据种子

tests/
├── Feature/           # 功能测试
└── Unit/             # 单元测试
```

## 开发规范

### 代码质量

```bash
# 代码格式化
./vendor/bin/pint

# 静态分析
./vendor/bin/phpstan analyse

# 运行测试
php artisan test
```

### 开发流程

1. 遵循功能优先开发模式
2. 使用 PSR-12 编码规范
3. 统一异常处理 (ApiResponseException)
4. 分类日志记录
5. 代码审查和优化
6. 根据需要编写测试（可选）

## 核心功能

### 证书管理

- 支持 DV、OV、EV 等多种证书类型
- 自动域名验证 (DNS、HTTP、Email)
- 证书状态实时同步
- 批量操作支持

### 订单系统

- 完整的订单生命周期管理
- 支付集成 (支付宝、微信)
- 自动扣费和发票生成
- 订单状态追踪

### 用户管理

- 多级用户权限体系
- API 访问控制
- 操作审计日志
- 资金账户管理

### 系统集成

- 多 CA 品牌适配器模式
- 异步任务处理
- 回调通知机制
- 缓存优化策略

## 监控与日志

系统提供完整的日志记录和监控功能：

- **API 日志**: 记录所有 API 调用
- **用户日志**: 用户操作审计
- **管理员日志**: 管理员操作审计
- **CA 日志**: CA 接口调用记录
- **错误日志**: 系统错误追踪
- **回调日志**: 回调处理记录

## 安全特性

- JWT 令牌认证和刷新机制
- API 访问频率限制
- 敏感数据加密存储
- SQL 注入和 XSS 防护
- 操作权限验证
- 审计日志记录

## 性能优化

- Redis 缓存策略
- 数据库查询优化
- 异步队列处理
- 防重复提交机制
- 分页查询支持

## 最近更新

### 通知系统优化 (2025-11-21)

#### 🔄 破坏性变更

**NotificationCenter::dispatch() 返回值变更**
- 从 `array` 改为 `void`，不再返回任何值
- 通知调度失败会在 debug 模式下记录日志
- 通知发送状态保存在数据库的 `notifications` 表中

```php
// ❌ 旧代码
$result = $notificationCenter->dispatch($intent);

// ✅ 新代码
$notificationCenter->dispatch($intent);
// 查询发送状态：Notification::where(...)->first()
```

#### ✨ 功能改进

1. **修复线程安全问题** - 移除所有 `chdir()` 调用，改用绝对路径操作文件
2. **统一文件清理** - 使用 `File::deleteDirectory()` 和 `File::delete()` 替代 `exec rm` 和原生 `unlink`，更安全可靠
3. **规范化返回值** - Channel 接口统一返回格式 `['code' => int, 'msg' => string]`
4. **优化通道过滤** - 拆分复杂逻辑，添加详细注释
5. **重构命名** - 将 `NotificationService` 重命名为 `NotificationRepository`，更准确地反映其数据访问层职责

## 贡献指南

1. Fork 项目
2. 创建功能分支 (`git checkout -b feature/AmazingFeature`)
3. 提交更改 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 创建 Pull Request

## 许可证

本项目采用 MIT 许可证 - 查看 [LICENSE](LICENSE) 文件了解详情。

## 支持

如有问题或建议，请通过以下方式联系：

- 创建 Issue
- 发送邮件至项目维护者
- 查看项目文档和 Wiki
