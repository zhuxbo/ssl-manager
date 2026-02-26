# 前端开发规范

## 技术栈

- **框架**: Vue 3 + TypeScript
- **UI**: Element Plus
- **状态管理**: Pinia
- **路由**: Vue Router
- **HTTP**: Axios
- **构建**: Vite
- **样式**: Sass + TailwindCSS
- **包管理**: pnpm 9+ (workspace)

## Monorepo 架构

```
frontend/
├── shared/     # 共享代码库
├── admin/      # 管理端应用
├── user/       # 用户端应用
└── base/       # 上游框架（只读）
```

### base 目录规则

- **只读** - 通过 git subtree 同步上游代码，不要修改
- 本地开发需执行 `cd base && pnpm install --ignore-workspace`

---

## 共享包 (shared)

使用 `@shared/*` 别名访问：

```typescript
// 组件
import { ReDialog } from "@shared/components/ReDialog";
import { ReRemoteSelect } from "@shared/components/ReRemoteSelect";
import { useRenderIcon } from "@shared/components/ReIcon";

// 工具函数
import { message, http, emitter } from "@shared/utils";

// 指令
import * as directives from "@shared/directives";
```

### 可用模块

| 别名 | 内容 |
|------|------|
| `@shared/components` | ReIcon, ReDialog, Auth, Perms, PureTableBar 等 |
| `@shared/utils` | http, auth, message 等 |
| `@shared/directives` | auth, perms, copy 等 |

### 依赖注入初始化

shared 模块使用依赖注入，需在应用启动时初始化。参考 `admin/src/utils/setup.ts`：

```typescript
import { createAuth, createHttp } from "@shared/utils";
import { setHasAuth } from "@shared/directives/auth";

// 初始化 Auth、Http 和权限指令
```

---

## 项目结构

```
src/
├── api/            # API 接口定义
├── assets/         # 静态资源
├── components/     # 公共组件
├── config/         # 配置文件
├── directives/     # 自定义指令
├── layout/         # 布局组件
├── plugins/        # 插件配置
├── router/         # 路由配置
├── store/          # 状态管理
├── style/          # 全局样式
├── utils/          # 工具函数
├── views/          # 页面组件
├── App.vue
└── main.ts
```

---

## 开发命令

```bash
# 在 monorepo 根目录运行
pnpm install          # 安装依赖

pnpm dev              # 同时启动 admin + user
pnpm dev:admin        # 仅管理端 (localhost:5173)
pnpm dev:user         # 仅用户端 (localhost:5174)

pnpm build            # 构建所有前端
pnpm build:admin      # 仅构建管理端
pnpm build:user       # 仅用户端

# 代码检查
pnpm lint:eslint      # ESLint
pnpm lint:prettier    # Prettier
pnpm lint:stylelint   # Stylelint
pnpm lint             # 全部检查
pnpm typecheck        # 类型检查
```

---

## 配置

### Platform Config

`public/platform-config.json` 核心配置：

**管理端 (admin)**:
```json
{
  "BaseUrlApi": "http://localhost:5300/admin",
  "Brands": ["certum", "gogetssl", "positive", "geotrust", "digicert", "ssltrus", "trustasia"]
}
```

**用户端 (user)**:
```json
{
  "BaseUrlApi": "http://localhost:5300",
  "Brands": ["certum", "gogetssl", "positive", "ssltrus", "trustasia"],
  "Beian": "豫ICP备123456789号"
}
```

---

## 开发规范

### 代码组织

- 按功能模块组织
- TypeScript 类型安全
- Vue 3 Composition API
- 单文件组件 (.vue)

### API 接口

- 统一 HTTP 请求封装
- 请求响应拦截器
- 错误统一处理
- 支持请求取消

### 状态管理

- Pinia 按模块划分
- 支持持久化存储

### 字典显示

- 表格中用 `options.find()?.label` 渲染字典值时，必须用 `?? row.xxx` 回落显示原值，防止插件卸载后字典不全导致空白
- 插件通过 `dictionaries` 机制（`mergePluginDictionaries`）在运行时追加 options 和 map

### 样式

- Sass 预处理器
- TailwindCSS 工具类
- 响应式设计
- 主题定制化

---

## 环境要求

- Node.js >= 18.18.0
- pnpm >= 9.0.0
