# SSL 后端安装手册

## 系统要求

- PHP 8.3 或更高版本
- MySQL 5.7+
- Redis 服务器
- Composer 2.8+
- **JRE (Java Runtime Environment) 17 或更高版本**
  - 用于生成 JKS 格式证书功能，需要 `keytool` 工具
  - 详细安装指南请参阅 [JRE_INSTALL.md](./JRE_INSTALL.md)
  - 注意：只需要 keytool 工具，安装 JRE 即可，无需完整的 JDK
- 以下 PHP 扩展必须启用：
  - bcmath
  - calendar
  - fileinfo
  - gd
  - iconv
  - intl
  - json
  - openssl
  - pcntl
  - pdo
  - redis
  - zip
  - mbstring
- **必须启用 PHP `exec`、`putenv` 和 `pcntl_signal` 函数**：
  - `exec` 函数用于执行外部程序，对于系统运行必不可少
  - `putenv` 函数用于设置环境变量，Laravel 应用配置需要此函数
  - `pcntl_signal` 函数用于队列工作进程的信号处理，系统队列功能必需
  - `pcntl_alarm` 函数用于队列工作进程的超时处理，系统队列功能必需
- **推荐启用 PHP `proc_open` 函数**：
  - `proc_open` 函数用于提升 Composer 性能，支持原生 unzip 和 7z 解压
  - 禁用时会影响 Composer 性能，但不阻止系统基本运行

## 安装步骤

### 1. 克隆项目

```bash
git clone git@gitee.com:zhuxbo/cert-manager-backend.git ssl-project
cd ssl-project
```

宝塔面板先进入 wwwroot 目录 然后使用如下命令

```bash
sudo -u www git clone git@gitee.com:zhuxbo/cert-manager-backend.git ssl-project
cd ssl-project
```

### 2. 安装依赖

#### 开发环境安装

```bash
composer install
```

#### 生产环境安装

在生产环境中，应该使用优化的安装方式以获得更好的性能：

```bash
# 生产环境安装 - 不安装开发依赖，启用优化
composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# 如果需要进一步优化（适用于高流量网站）
composer dump-autoload --optimize --classmap-authoritative
```

**注意：** 自动安装向导已默认使用生产模式安装 Composer 依赖，无需手动执行上述命令。

**生产环境安装参数说明：**

- `--no-dev`: 不安装开发依赖（如测试工具、调试工具等）
- `--optimize-autoloader`: 优化自动加载器，提高性能
- `--no-interaction`: 非交互模式，适合自动化部署
- `--no-scripts`: 跳过脚本执行，避免安装过程中的潜在问题
- `--classmap-authoritative`: 生成权威类映射，进一步提升性能（可选）

### 3. 环境配置

项目已配置自动执行环境配置脚本，在运行 `composer install` 后将会：

1. 自动将 `.env.example` 复制为 `.env`（如果不存在）
2. 生成应用密钥 APP_KEY
3. 生成 JWT 密钥 JWT_SECRET

以上步骤均会自动执行，无需手动操作。如果自动配置失败，可以手动执行以下命令：

```bash
# 复制环境变量文件
cp .env.example .env

# 生成应用密钥 (首次安装)
php artisan key:generate

# 生成JWT密钥 (首次安装)
php artisan jwt:secret
```

### 4. ⚠️ 重要安全说明 - 密钥管理

- **首次安装**: 使用上述命令生成密钥
- **生产环境**: 密钥生成后**绝对不要**重新生成，否则会导致：
  - `APP_KEY` 重新生成：所有加密数据无法解密，现有会话失效
  - `JWT_SECRET` 重新生成：所有用户 token 失效，需要重新登录
- **如果必须重新生成密钥** (如密钥泄露)：

    ```bash
    # 强制重新生成 (谨慎使用)
    php artisan key:generate --force
    php artisan jwt:secret --force
    ```

- **备份建议**: 生产环境应备份 `.env` 文件中的密钥

完成自动配置后，您仍需编辑 `.env` 文件，配置数据库和其他必要设置：

````env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=你的数据库名
DB_USERNAME=你的数据库用户名
DB_PASSWORD=你的数据库密码

# 设置Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# 设置允许跨域的源 支持通配符
ALLOWED_ORIGINS=localhost

### 4. 数据库迁移

```bash
php artisan migrate
````

### 5. 生成后台初始管理员及会员基础级别

- 管理员初始账号 admin 密码 123456

```bash
php artisan db:seed
```

### 6. 启动应用

#### 开发环境

```bash
php artisan serve
```

默认情况下，应用将在 <http://localhost:8000> 运行

#### 生产环境

1. 使用 Nginx 配置虚拟主机，将根目录指向`public`文件夹。

2. 配置伪静态规则

    ```nginx
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    ```

#### Docker 部署（推荐）

项目提供了完整的 Docker 部署方案，使用 Docker 可以快速部署并减少环境配置问题。

详细的 Docker 部署指南请参见：[./DOCKER_INSTALL.MD](./DOCKER_INSTALL.MD)

## PHP 特殊设置

- 确保 PHP 的内存限制足够大，建议至少设置为 128M：`memory_limit=128M`
- 确保 PHP-FPM 或 Nginx 的工作进程数配置合理，以处理并发请求
- **确保启用了 PHP `exec`、`putenv`、`pcntl_signal`、`pcntl_alarm` 和 `proc_open` 函数**，在 php.ini 中设置：

    ```txt
    disable_functions = ... # 确保此列表中不包含exec、putenv、pcntl_signal、pcntl_alarm和proc_open
    ```

    或在宝塔面板 PHP 设置中的"禁用函数"列表中移除 exec、putenv、pcntl_signal、pcntl_alarm 和 proc_open

    **重要**: `exec`、`putenv`、`pcntl_signal`、`pcntl_alarm` 函数是系统必需的，被禁用会导致安装失败
    **注意**: `proc_open` 函数主要用于提升 Composer 性能，如果无法启用也不会阻止系统运行

## 开发调试工具

### Laravel Tinker

项目已安装 Laravel Tinker 用于开发调试。Tinker 是一个强大的 REPL 工具，可以让你在命令行中与 Laravel 应用交互。

**注意：** Tinker 作为开发依赖安装在 `require-dev` 中，在生产环境使用 `composer install --no-dev` 时不会被安装。

#### 启动 Tinker

```bash
php artisan tinker
```

#### 常用 Tinker 命令示例

```php
# 查看所有用户
App\Models\User::all();

# 创建测试用户
$user = new App\Models\User();
$user->name = 'Test User';
$user->email = 'test@example.com';
$user->save();

# 查看应用配置
config('app.name');

# 测试队列任务
dispatch(new App\Jobs\TestJob());

# 清除缓存
cache()->flush();

# 退出 Tinker
exit;
```

**注意：** Tinker 仅适用于开发环境，生产环境中请谨慎使用。

## 队列和计划任务设置

### 配置队列

1. 确保`.env`文件中设置了`QUEUE_CONNECTION=redis`
2. 启动队列工作进程：

```bash
php artisan queue:work --queue Task
```

**注意：** 队列进程需要 www 用户运行（宝塔面板）

### 设置计划任务

在服务器上添加以下 Cron 条目：

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

宝塔面板在定时任务添加如下脚本，选择 www 用户运行：

```bash
cd /path-to-your-project && php artisan schedule:run
```

注意：如果使用 Docker 部署，队列和计划任务会自动配置和启动，无需手动设置。

## 文件权限设置

请确保以下目录可写：

```bash
chmod -R 775 storage bootstrap/cache
```

如果使用的是 Linux 服务器，还需确保目录的用户组设置正确：

```bash
chown -R $USER:www-data storage bootstrap/cache
```

## 常见问题排查

1. **500 服务器错误**

    - 检查 `storage/logs` 下的日志文件获取详细错误信息
    - 确保所有必需的 PHP 扩展已安装

2. **数据库连接问题**

    - 验证`.env`文件中的数据库凭据
    - 确保数据库服务正在运行

3. **Redis 连接失败**

    - 检查 Redis 服务是否正在运行
    - 验证`.env`中的 Redis 配置是否正确

4. **权限问题**

    - 确保`storage`和`bootstrap/cache`目录可写
    - 检查 web 服务器用户是否有适当的权限

5. **JWT 相关问题**

    - 项目已预先配置好 JWT，现在 `composer install` 会自动运行 `jwt:secret` 命令生成密钥
    - 如果遇到 JWT 相关错误，确认`.env`文件中存在有效的`JWT_SECRET`

6. **PHP 函数被禁用问题**

    - **必须启用的函数**: `exec`、`putenv`、`pcntl_signal`、`pcntl_alarm`
        - `exec`: 错误信息：`Call to undefined function exec()`，用于执行外部程序
        - `putenv`: 错误信息：`Call to undefined function putenv()`，用于设置环境变量
        - `pcntl_signal`: 错误信息：`Call to undefined function pcntl_signal()`，用于队列工作进程的信号处理
        - `pcntl_alarm`: 错误信息：`Call to undefined function pcntl_alarm()`，用于队列工作进程的超时处理
        - 这些函数被禁用会导致安装失败
    - **推荐启用的函数**: `proc_open`
        - `proc_open`: 被禁用时 Composer 会显示警告，但不会阻止安装，可能导致解压性能下降和文件权限丢失
    - **解决方案**:
        - 在 PHP 配置文件中检查 `disable_functions` 设置
        - 对于宝塔面板，在 PHP 管理中检查"禁用函数"列表
        - 使用 Docker 部署时，已预配置启用这些函数

## JRE 安装 (可选)

JRE (Java Runtime Environment) 是可选组件，主要用于生成 JKS 格式证书和使用 `keytool` 工具。

如果您需要使用 JKS 格式证书或相关 Java 功能，请参阅详细的安装指南：

📖 **[JRE 安装指南](./JRE_INSTALL.md)**

该文档包含：

- 各平台详细安装步骤 (Linux、macOS、Windows)
- 环境变量配置
- 版本管理工具使用
- 常见问题解决方案
- 验证和测试方法

**注意**: 我们只需要 `keytool` 工具，因此安装 JRE 17 即可满足需求，无需完整的 JDK。

如果您不需要 JKS 证书功能，可以跳过 JRE 安装。
