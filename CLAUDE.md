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
