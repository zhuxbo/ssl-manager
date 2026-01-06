# Manager Monorepo

## 工作流程

- **base 目录只读**：通过 git subtree 同步上游代码，不要修改
- **base 依赖**：本地开发需在 base 目录执行 `pnpm install --ignore-workspace`

## 包结构

```
frontend/
├── shared/     # 共享代码库（组件、工具、指令、构建工具）
├── admin/      # 管理端应用
└── user/       # 用户端应用
backend/        # Laravel 11 后端
build/          # Docker 容器化构建
deploy/         # 部署脚本
```

## 共享包 (shared)

使用 `@shared/*` 别名访问：
- `@shared/components` - ReIcon, ReDialog, Auth, Perms, PureTableBar 等
- `@shared/utils` - http, auth, message 等
- `@shared/directives` - auth, perms, copy 等

shared 模块使用依赖注入，需在应用启动时初始化（见 `admin/src/utils/setup.ts`）。

## 开发命令

```bash
pnpm dev           # 同时启动 admin 和 user
pnpm dev:admin     # 仅启动 admin
pnpm build         # 构建所有前端
```

## 生产构建

```bash
cd build
./build.sh --test           # 测试构建
./build.sh --prod           # 生产构建（推送）
./build.sh --test admin     # 仅构建管理端
```

定制目录 `build/custom/` 存放 `build.env`、`config.json`、logo 等定制资源，不纳入版本控制。

## 在线升级

版本号在 `version.json` 配置，升级服务位于 `backend/app/Services/Upgrade/`。

```bash
php artisan upgrade:check              # 检查更新
php artisan upgrade:run                # 执行升级
php artisan upgrade:rollback           # 回滚
```

## 部署

```bash
# 自动检测环境
curl -fsSL https://gitee.com/zhuxbo/cert-manager/raw/main/deploy/install.sh | bash

# 指定 Docker/宝塔
bash -s docker  # 或 bash -s bt

# 强制使用国内 Composer 镜像
FORCE_CHINA_MIRROR=1 bash install.sh
```

脚本自动检测网络环境（云服务商元数据 + GitHub 可达性），决定是否使用阿里云镜像。

## Auto API

自动部署工具 API，通过 `refer_id` 认证：

```http
Authorization: Bearer <refer_id>
```

回调接口：`POST /api/auto/callback`
