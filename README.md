# Manager - SSL 证书管理系统

基于 Monorepo 架构的多级代理 SSL 证书管理系统。

## 项目结构

```
manager/
├── backend/                # Laravel 11 后端 API
├── frontend/
│   ├── base/              # Pure Admin Thin (subtree, 只读参考)
│   ├── shared/            # 共享代码包
│   ├── admin/             # 管理端前端
│   ├── user/              # 用户端前端
│   └── easy/              # 简易证书工具
├── build/                 # Docker 容器化构建系统
├── package.json           # 根配置
└── pnpm-workspace.yaml    # workspace 配置
```

## 技术栈

| 组件   | 技术                                                   |
| ------ | ------------------------------------------------------ |
| 后端   | Laravel 11, PHP 8.3+, MySQL, Redis                     |
| 前端   | Vue 3, TypeScript, Element Plus, Vite, Tailwind CSS v4 |
| 包管理 | pnpm workspace (monorepo)                              |
| 构建   | Docker 容器化，支持增量构建和缓存复用                  |

## 快速开始

### 开发环境

```bash
# 安装依赖
pnpm install

# 启动开发服务器
pnpm dev           # 同时启动 admin 和 user
pnpm dev:admin     # 仅启动管理端
pnpm dev:user      # 仅启动用户端

# 构建
pnpm build         # 构建所有前端
pnpm build:admin   # 仅构建管理端
pnpm build:user    # 仅构建用户端
```

### 后端开发

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

## 目录详解

### 前端模块

| 目录              | 说明                                                            |
| ----------------- | --------------------------------------------------------------- |
| `frontend/base`   | Pure Admin Thin 纯净版，通过 subtree 同步上游，**只读不可修改** |
| `frontend/shared` | 共享代码包（组件、工具函数、指令），供 admin/user 引用          |
| `frontend/admin`  | 管理端应用，系统管理员使用                                      |
| `frontend/user`   | 用户端应用，代理商/用户使用                                     |
| `frontend/easy`   | 简易证书工具（纯 HTML/JS，无需构建）                            |

### 后端模块

| 目录       | 说明                              |
| ---------- | --------------------------------- |
| `backend/` | Laravel 11 后端，提供 RESTful API |

### 构建系统

| 目录     | 说明                        |
| -------- | --------------------------- |
| `build/` | Docker 容器化构建脚本和配置 |

## Shared 共享包

admin 和 user 通过 `@shared/*` 别名引用共享代码：

```typescript
// 导入工具函数
import { message, emitter, NProgress } from "@shared/utils";

// 导入指令
import * as directives from "@shared/directives";

// 导入组件
import {
  ReIcon,
  ReDialog,
  ReSegmented,
  Auth,
  Perms,
  PureTableBar
} from "@shared/components";

// 导入配置
import { getConfig, getPlatformConfig } from "@shared/config";
```

### Shared 包结构

```
frontend/shared/src/
├── config/         # 平台配置（getConfig、getPlatformConfig）
├── utils/          # 工具函数
│   ├── auth/       # 认证工具（工厂函数模式）
│   ├── http/       # HTTP 请求（工厂函数模式）
│   └── ...         # message、emitter、NProgress、tree 等
├── directives/     # 自定义指令（copy、longpress、ripple、auth、perms 等）
├── components/     # 通用组件
│   ├── ReIcon/     # 图标组件
│   ├── ReDialog/   # 对话框组件
│   ├── ReAuth/     # 权限按钮组件
│   ├── RePerms/    # 角色权限组件
│   ├── RePureTableBar/  # 表格工具栏组件
│   └── ...
├── hooks/          # 通用 hooks
└── types/          # 类型定义
```

### 工厂函数模式

auth 和 http 等模块通过工厂函数 + 依赖注入实现跨应用复用：

```typescript
// admin/src/utils/setup.ts 或 user/src/utils/setup.ts
import { createAuth, createHttp } from "@shared/utils";
import { useUserStoreHook } from "@/store/modules/user";

export function setupSharedModules() {
  createAuth({
    getIsRemembered: () => useUserStoreHook().isRemembered,
    setUsername: username => useUserStoreHook().SET_USERNAME(username)
    // ... 其他 store hooks
  });

  createHttp({
    refreshToken: data => useUserStoreHook().handRefreshToken(data),
    logout: () => useUserStoreHook().logOut()
  });
}
```

## 生产构建

构建系统位于 `build/` 目录，使用 Docker 容器化构建确保环境一致性。

### 基本用法

```bash
cd build

# 测试构建（不推送）
./build.sh --test

# 生产构建（推送到 production-code 仓库）
./build.sh --prod

# 指定模块构建
./build.sh --test admin    # 仅构建管理端
./build.sh --test api      # 仅构建后端
./build.sh --test user     # 仅构建用户端

# 其他选项
./build.sh --test --force-build     # 强制构建（忽略缓存）
./build.sh --test --clear-cache     # 清空依赖缓存后构建
./build.sh --test --rebuild-image   # 重建 Docker 镜像
./build.sh --test --compare         # 构建后对比远程差异
```

### 构建目录结构

```
build/
├── build.sh              # 主控脚本（宿主机运行）
├── Dockerfile.base       # 基础镜像（PHP 8.3 + Node 22）
├── Dockerfile.build      # 构建镜像
├── config.json           # 构建配置（仓库地址、排除规则等）
├── build.env             # 环境变量（镜像名、密钥路径等）
├── nginx/                # Nginx 配置模板
├── web/                  # Web 静态文件
├── scripts/              # 容器内构建脚本
│   ├── container-build.sh    # 容器主入口
│   ├── build-backend.sh      # 后端构建（composer install）
│   ├── build-frontend.sh     # 前端构建（pnpm build）
│   └── sync-to-production.sh # 同步到生产仓库
├── custom/               # 定制目录（.gitignore，可选）
│   ├── build.env         # 覆盖默认环境变量
│   ├── config.json       # 覆盖默认配置
│   ├── logo.svg          # 覆盖 admin/user 的 logo
│   ├── favicon.ico       # 覆盖 web 的 favicon
│   └── qrcode.png        # 覆盖 user 的二维码
└── temp/                 # 临时目录（.gitignore）
    ├── production-code/  # 生产代码 Git 仓库
    ├── caches/           # pnpm/composer 依赖缓存
    └── reports/          # 构建日志
```

### 定制构建

通过 `custom/` 目录可以定制构建配置和资源，不纳入版本控制：

**custom/build.env** - 配置推送密钥和镜像名：

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

**custom/config.json** - 覆盖生产仓库和 Git 用户：

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

### 构建约束

- **容器内存限制 2G** - 前端构建使用 `NODE_OPTIONS=--max-old-space-size=1536`
- **跳过类型检查** - 生产构建跳过 `vue-tsc` 类型检查以节省内存（见下方说明）
- **temp 目录持久化** - 挂载到宿主机，容器销毁后保留缓存和产物
- **custom 目录不提交** - 存放各环境特定的定制配置

### 类型检查

**本地构建**会执行类型检查，**Docker 生产构建**跳过类型检查（2G 内存限制下 `vue-tsc` 无法运行）。

```bash
# 本地构建（包含类型检查）
pnpm build              # 构建所有前端，包含类型检查
pnpm build:admin        # 构建 admin，包含类型检查
pnpm build:user         # 构建 user，包含类型检查

# 仅执行类型检查（不打包）
pnpm typecheck          # 检查所有前端项目
pnpm --filter admin typecheck
pnpm --filter user typecheck
```

**构建脚本说明：**

| 脚本           | 类型检查 | 打包 | 使用场景        |
| -------------- | -------- | ---- | --------------- |
| `build`        | ✅       | ✅   | 本地构建        |
| `build:bundle` | ❌       | ✅   | Docker 生产构建 |
| `typecheck`    | ✅       | ❌   | 仅检查类型      |

### 构建流程

```
build.sh (宿主机)
    │
    ├─ 构建 Docker 镜像（基础镜像 + 构建镜像）
    │
    └─ 启动容器 (--memory=2g)
        │
        ├─ /source:ro     ← monorepo 根目录（只读）
        ├─ /workspace     ← temp 工作目录
        └─ /build/custom  ← custom 定制目录（只读）
        │
        └─▶ container-build.sh (容器内)
             │
             ├─ 准备阶段: 复制源码，应用定制资源
             │
             ├─ 后端构建: composer install --no-dev
             │
             ├─ 前端构建: pnpm install && vite build (跳过类型检查)
             │
             ├─ 同步产物到 production-code
             │
             └─ 推送 (仅 --prod 模式)
```

## 同步 Pure Admin 上游

```bash
# 同步 base 目录到最新的 Pure Admin Thin
pnpm sync:base

# 查看 base 的变更
git diff HEAD~1 -- frontend/base/
```

## 开发注意事项

1. **base 目录只读** - 通过 git subtree 同步上游代码，不要直接修改
2. **共享代码优先** - 新增通用功能应放入 `frontend/shared/`
3. **工厂函数模式** - auth、http 等依赖 store 的模块使用工厂函数注入依赖
4. **Tailwind 扫描** - 各应用的 `tailwind.css` 需添加 `@source` 指令扫描 shared 包
