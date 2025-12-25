# Manager Monorepo

## 工作流程

- **base 目录只读**：通过 git subtree 同步的上游代码，不要修改
- **base 依赖**：本地开发需在 base 目录执行 `pnpm install --ignore-workspace` 安装依赖

---

这是一个 pnpm monorepo 项目，包含以下包：

## 包结构

```
frontend/
├── shared/     # 共享代码库（组件、工具函数、指令、构建工具等）
│   ├── src/    # 源代码
│   └── build/  # 共享构建工具（Vite 插件配置等）
├── admin/      # 管理端应用
│   └── build/  # 仅 index.ts 入口，调用 shared/build
└── user/       # 用户端应用
    └── build/  # 仅 index.ts 入口，调用 shared/build
```

## 共享包 (shared)

### 别名配置

- admin 和 user 使用 `@shared/*` 别名访问 shared 包
- 配置在各应用的 `build/index.ts`（通过 `createBuildUtils` 工厂函数）和 `tsconfig.json` 中

### 构建工具共享

`shared/build/` 包含所有 Vite 构建工具：

- `utils.ts` - 工厂函数 `createBuildUtils(appRoot, pkg)` 生成 alias、pathResolve 等
- `plugins.ts` - Vite 插件列表
- `optimize.ts` - 预构建优化配置
- `cdn.ts` / `compress.ts` / `info.ts` - 其他构建工具

各应用的 `build/index.ts` 是薄层，负责初始化并重导出共享模块

### 导出结构

- `@shared/components` - 共享组件（ReIcon, ReDialog, ReRemoteSelect, Auth, Perms, PureTableBar 等）
- `@shared/utils` - 工具函数（http, auth, message 等）
- `@shared/directives` - 自定义指令（auth, perms, copy 等）
- `@shared/config` - 配置相关

### 初始化模式

shared 中的某些模块使用依赖注入模式，需要在应用启动时初始化：

1. **Auth 模块** - 需要调用 `createAuth()` 注入 store hooks
2. **Http 模块** - 需要调用 `createHttp()` 注入刷新 token 逻辑
3. **Auth 指令** - 需要调用 `setHasAuth()` 注入权限检查函数
4. **ReAuth 组件** - 需要调用 `setHasAuthForAuth()` 注入 hasAuth 函数
5. **RePureTableBar 组件** - 需要调用 `setEpThemeColorGetter()` 注入主题色 getter
6. **Responsive Storage** - 需要传入 `routerArrays` 参数

详见 `admin/src/utils/setup.ts` 和 `user/src/utils/setup.ts`

## Tailwind CSS 配置

Tailwind v4 的 `@tailwindcss/vite` 插件只自动扫描项目自身的 src 目录，不会扫描 workspace 包。为了让 shared 包中的 Tailwind 类名被正确生成，需要在各应用的 `tailwind.css` 中添加 `@source` 指令：

```css
@source "../../../shared/src/**/*.{vue,ts,tsx,js,jsx}";
```

配置文件位置：

- `frontend/admin/src/style/tailwind.css`
- `frontend/user/src/style/tailwind.css`

## ESLint 配置

Monorepo 中每个应用有独立的 `eslint.config.js`，需要设置 `tsconfigRootDir` 以避免解析冲突：

```javascript
// TypeScript 文件
...tseslint.config({
  languageOptions: {
    parserOptions: {
      tsconfigRootDir: import.meta.dirname
    }
  }
})

// Vue 文件
{
  files: ["**/*.vue"],
  languageOptions: {
    parserOptions: {
      tsconfigRootDir: import.meta.dirname
    }
  }
}
```

## 根目录配置

以下配置文件放在根目录，由所有应用共享：

- `.editorconfig` - 编辑器配置
- `.prettierrc.js` - Prettier 配置
- `.markdownlint.json` - Markdown lint 配置
- `commitlint.config.js` - Git commit 规范配置

各应用独立的配置（因路径或需求不同）：

- `eslint.config.js` - 需要设置 `tsconfigRootDir`
- `tsconfig.json` - 路径别名不同
- `.env*` - 应用特定环境变量

## 开发命令

```bash
pnpm dev           # 同时启动 admin 和 user
pnpm dev:admin     # 仅启动 admin
pnpm dev:user      # 仅启动 user
pnpm build         # 构建所有包
```

## 生产构建 (Docker)

构建脚本位于 `build/` 目录，使用 Docker 容器化构建：

```bash
cd build

# 测试构建（不推送）
./build.sh --test

# 生产构建（推送到 production-code 仓库）
./build.sh --prod

# 指定模块
./build.sh --test admin    # 仅构建管理端
./build.sh --test api      # 仅构建后端
./build.sh --test user     # 仅构建用户端

# 强制构建
./build.sh --test --force-build
```

### 构建目录结构

```
build/
├── build.sh              # 主控脚本（宿主机运行）
├── Dockerfile.base       # 基础镜像（PHP + Node）
├── Dockerfile.build      # 构建镜像
├── config.json           # 构建配置
├── build.env             # 环境变量
├── nginx/                # Nginx 配置
├── web/                  # Web 静态文件
├── scripts/              # 容器内脚本
│   ├── container-build.sh
│   ├── build-backend.sh
│   ├── build-frontend.sh
│   └── sync-to-production.sh
├── custom/               # 定制目录（.gitignore）
│   ├── build.env         # 覆盖构建环境变量（可选）
│   ├── config.json       # 覆盖 build 配置（可选）
│   ├── logo.svg          # 覆盖 admin/user 的 logo（可选）
│   ├── favicon.ico       # 覆盖 web 的 favicon（可选）
│   └── qrcode.png        # 覆盖 user 的 qrcode（可选）
└── temp/                 # 临时目录（.gitignore）
    ├── production-code/  # 生产代码仓库
    ├── caches/           # pnpm/composer 缓存
    └── reports/          # 构建日志
```

### 定制目录 (custom/)

`custom/` 目录用于存放定制化配置和资源，不纳入版本控制：

- **build.env** - 覆盖默认 `build.env`，可配置推送密钥等

  ```bash
  # 镜像配置
  BASE_IMAGE_NAME=ssl-manager-build-base
  BASE_IMAGE_TAG=ubuntu-24.04
  BUILD_IMAGE_NAME=ssl-manager-build
  BUILD_IMAGE_TAG=latest
  REBUILD_DAYS=30

  # Gitee 推送私钥路径（生产模式自动加载到 ssh-agent）
  GITEE_SSH_KEY=~/.ssh/gitee_id_rsa
  ```

- **config.json** - 覆盖默认 `config.json` 的 `build` 节点

  ```json
  {
    "build": {
      "production_repo": {
        "url": "git@gitee.com:your-org/your-repo.git"
      },
      "git_user": {
        "name": "your-name",
        "email": "your@email.com"
      }
    }
  }
  ```

- **logo.svg** - 覆盖 admin 和 user 的 `public/logo.svg`
- **favicon.ico** - 覆盖 web 的 `public/favicon.ico`
- **qrcode.png** - 覆盖 user 的 `public/qrcode.png`

### 约束条件

- **容器内存限制 2G** - 前端构建使用 `NODE_OPTIONS=--max-old-space-size=2048`
- **temp 目录持久化** - 挂载到宿主机，容器销毁后保留缓存和产物
- **custom 目录不提交** - 存放各环境特定的定制配置

## 在线升级功能

### 版本配置

版本号在根目录 `version.json` 中配置：

```json
{
  "version": "1.0.0",
  "channel": "main"
}
```

- **version**: 语义化版本号（SemVer）
- **channel**: 发布通道，`main` 为正式版，`dev` 为开发版

### 后端升级服务

升级相关服务类位于 `backend/app/Services/Upgrade/`：

- `VersionManager.php` - 版本信息管理和比较
- `ReleaseClient.php` - Gitee/GitHub Releases API 客户端
- `BackupManager.php` - 升级前备份和回滚
- `PackageExtractor.php` - 升级包解压和应用
- `UpgradeService.php` - 升级主服务（协调器）

### Artisan 命令

```bash
# 检查更新
php artisan upgrade:check

# 执行升级
php artisan upgrade:run              # 升级到最新版
php artisan upgrade:run 1.1.0        # 升级到指定版本
php artisan upgrade:run --force      # 跳过确认

# 回滚
php artisan upgrade:rollback                    # 显示备份列表
php artisan upgrade:rollback 2025-01-01_120000  # 回滚到指定备份
```

### API 端点

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/admin/upgrade/version` | 获取当前版本信息 |
| GET | `/admin/upgrade/check` | 检查更新 |
| GET | `/admin/upgrade/releases` | 获取历史版本列表 |
| POST | `/admin/upgrade/execute` | 执行升级 |
| GET | `/admin/upgrade/backups` | 列出备份 |
| POST | `/admin/upgrade/rollback` | 执行回滚 |

### 发布 Release

```bash
cd build

# 构建包
./scripts/package.sh

# 发布到 Gitee Releases
GITEE_ACCESS_TOKEN=xxx ./scripts/release.sh --version 1.0.0
```

## 部署脚本

### 目录结构

```
deploy/
├── install.sh              # 远程一键安装入口
├── deploy.sh               # 旧版部署脚本（保留兼容）
├── scripts/
│   ├── common.sh           # 公共函数库
│   ├── bt-install.sh       # 宝塔面板安装脚本
│   ├── bt-deps.sh          # 宝塔依赖检测
│   ├── docker-install.sh   # Docker 交互式安装脚本
│   └── upgrade.sh          # 升级辅助脚本
├── docker/                 # Docker 配置模板
└── nginx/                  # Nginx 配置模板
```

### 一键安装

```bash
# 自动检测环境（推荐）
curl -fsSL https://gitee.com/zhuxbo/cert-manager/raw/main/deploy/install.sh | bash

# 指定 Docker 部署
curl -fsSL https://gitee.com/zhuxbo/cert-manager/raw/main/deploy/install.sh | bash -s docker

# 指定宝塔部署
curl -fsSL https://gitee.com/zhuxbo/cert-manager/raw/main/deploy/install.sh | bash -s bt
```

### 脚本包内容

Release 时生成的 `ssl-manager-script-{version}.zip` 包含：

```
script-deploy/
├── scripts/
│   ├── common.sh           # 公共函数（下载、镜像源、检测等）
│   ├── bt-install.sh       # 宝塔安装（仅支持宝塔环境）
│   ├── bt-deps.sh          # 宝塔依赖检测
│   ├── docker-install.sh   # Docker 交互式安装
│   └── upgrade.sh          # 升级辅助脚本
├── deploy.sh               # 旧版入口（保留兼容）
├── manager.conf            # Nginx 配置模板
└── README.md               # 使用说明
```

### 宝塔部署 (`bt-install.sh`)

**适用环境**: 已安装宝塔面板的服务器

**功能**:
- 检测宝塔面板环境
- 选择 PHP 版本（8.3+）
- 检测 PHP 扩展依赖
- 下载完整程序包（优先 Gitee）
- 部署代码和设置权限
- 显示 Nginx 配置提示

**注意**: 非宝塔环境会提示使用 Docker 部署

### Docker 部署 (`docker-install.sh`)

**适用环境**: 任何 Linux 服务器

**7 步交互式配置向导**:
1. Docker 环境检测/自动安装
2. 镜像源选择（中国大陆/国际/自动检测）
3. MySQL 配置（容器化/外部）
4. Redis 配置（容器化/外部）
5. 端口配置（自动检测冲突）
6. SSL 证书配置（可选，用户提供证书路径）
7. 安装目录选择

**镜像源配置**:
- 中国大陆: Docker 镜像加速、Alpine 阿里云镜像、Composer 阿里云镜像
- 国际: 使用官方源

**动态生成文件**:
- `docker/Dockerfile` - PHP 8.3 镜像（根据区域配置镜像源）
- `config/nginx/default.conf` - Nginx 配置
- `config/nginx/ssl.conf` - SSL 配置（如启用）
- `docker-compose.yml` - 服务编排

### 下载优先级

所有下载（脚本和后端升级）都遵循：**Gitee 优先，GitHub 回退**

**Shell 函数** (`common.sh`):
```bash
download_release_file "filename.zip" "/save/path" "latest"
download_and_extract_full "/dest/dir" "latest"
```

**PHP 方法** (`ReleaseClient.php`):
```php
$client->downloadUpgradePackage($release, $savePath);
$client->downloadFullPackage($release, $savePath);
$client->downloadPackageWithFallback($filename, $tag, $savePath);
```

### 仓库配置

下载源硬编码在脚本和后端中：
- **Gitee**: `https://gitee.com/zhuxbo/cert-manager`
- **GitHub**: `https://github.com/zhuxbo/cert-manager`

## Web 安装向导

### 目录结构

```
backend/public/
├── install.php                    # 入口文件（~30行）
└── install-assets/                # 安装资源目录（安装后可删除）
    ├── autoload.php               # PSR-4 自动加载器
    ├── InstallController.php      # 请求控制器
    ├── DTO/
    │   ├── CheckResult.php        # 检查结果对象
    │   └── InstallConfig.php      # 安装配置对象
    ├── Checker/
    │   ├── RequirementChecker.php # 系统环境检查协调器
    │   ├── PhpChecker.php         # PHP 版本和扩展检查
    │   ├── FunctionChecker.php    # PHP 函数检查
    │   ├── PermissionChecker.php  # 目录权限检查
    │   └── ToolChecker.php        # Composer/Java 检查
    ├── Connector/
    │   ├── DatabaseConnector.php  # 数据库连接测试
    │   └── RedisConnector.php     # Redis 连接测试
    ├── Installer/
    │   ├── InstallExecutor.php    # 安装执行协调器
    │   ├── EnvConfigurator.php    # .env 文件配置
    │   ├── ComposerRunner.php     # Composer 安装
    │   ├── KeyGenerator.php       # 密钥生成
    │   ├── DatabaseMigrator.php   # 数据库迁移
    │   └── Cleaner.php            # 安装文件清理
    ├── View/
    │   ├── Renderer.php           # 页面渲染器
    │   └── ProgressReporter.php   # 安装进度输出
    └── *.html / *.css / *.js      # 静态资源
```

### 模块说明

- **入口文件** (`install.php`): 仅加载自动加载器并调用控制器
- **DTO**: 数据传输对象，用于类型安全的配置传递
- **Checker**: 系统环境检查器，验证 PHP、扩展、函数、权限等
- **Connector**: 数据库和 Redis 连接测试
- **Installer**: 安装执行器，按步骤执行 Composer 安装、密钥生成、数据库迁移等
- **View**: 页面渲染和进度报告

### 安装流程

1. 系统环境检查（PHP 8.3+、扩展、函数、目录权限、Composer）
2. 配置表单（数据库、Redis 连接信息）
3. 连接测试（数据库连接、空库验证、Redis 认证）
4. 执行安装（.env 配置、Composer、密钥生成、迁移、优化）
5. 自动清理安装文件
