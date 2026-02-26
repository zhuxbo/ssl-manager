# 插件开发规范

## 目录结构

```
plugins/
├── build-plugin.sh          # 通用构建脚本
├── temp/                    # 构建产物（git 忽略）
├── README.md                # 安装/更新/卸载说明
└── {name}/                  # 插件目录
    ├── plugin.json          # 插件元数据（必须）
    ├── build.json           # 打包配置
    ├── backend/             # PHP 后端
    │   ├── {Name}ServiceProvider.php
    │   ├── Controllers/
    │   ├── Models/
    │   ├── Requests/
    │   ├── routes/
    │   └── migrations/
    ├── admin/               # 管理端前端（Vite IIFE）
    │   ├── src/index.ts     # 入口，导出 routes
    │   ├── vite.config.ts
    │   └── dist/            # 构建产物（git 忽略）
    ├── user/                # 用户端前端（Vite IIFE）
    ├── frontend/            # 静态页面（可选）
    └── nginx/               # nginx 配置（可选）
```

---

## plugin.json

```json
{
  "name": "easy",
  "version": "0.0.1",
  "description": "插件描述",
  "provider": "EasyServiceProvider",
  "admin_bundle": "admin/easy-plugin.iife.js",
  "admin_css": "admin/easy-plugin-admin.css",
  "user_bundle": "user/easy-plugin.iife.js",
  "release_url": ""
}
```

- `provider`：ServiceProvider 类名，位于 `backend/{Provider}.php`
- `admin_bundle` / `user_bundle`：前端 IIFE 入口，相对于插件目录的路径
- `release_url`：第三方更新地址（留空则使用主系统 release 子目录）

---

## 后端开发

### ServiceProvider

每个插件有一个 ServiceProvider，由 `PluginServiceProvider` 自动扫描注册。

- 命名空间：`Plugin\{Name}\`（自动注册，基于 `plugins/{name}/backend/`）
- 路由注册：在 `boot()` 中加载 `routes/*.php`
- 日志处理：实现 `PluginLogHandler` 接口注册到主系统日志

```php
namespace Plugin\Easy;

use Illuminate\Support\ServiceProvider;

class EasyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes/api.php');
        $this->loadRoutesFrom(__DIR__ . '/routes/admin.php');
        // ...
    }
}
```

### 路由

- 使用主系统中间件（`auth:api`、`admin` 等）
- 路由前缀遵循主系统约定：`api/admin/`、`api/user/`、`api/easy/`（公共）、`api/callback/`

### 数据库

- 迁移文件放 `backend/migrations/`，安装时由 `loadMigrationsFrom()` 自动执行
- 表名建议加插件前缀（如 `easy_logs`）避免冲突
- 卸载时可选回滚迁移（`remove_data=true`）
- **插件表独立管理**：主系统 `db:structure --export` 通过 `--path=database/migrations` 排除插件迁移，`structure.json` 仅包含主系统表
- **从主系统迁移分离时注意**：如果原来某些表在主系统迁移文件中，拆分到插件时必须确保主系统迁移文件仍保留主系统自己的表（不能整个删除包含多张表的迁移文件）

### 解耦原则

- 主系统**不**硬引用插件代码或表
- 主系统通过动态扫描兼容插件数据（如 `_logs` 后缀表 + `user_id` 字段）
- 插件可引用主系统的 Model、Service、Util

---

## 前端开发

### IIFE 打包

插件前端打包为 IIFE 格式，运行时由主系统动态加载。

```typescript
// admin/src/index.ts
const routes = [
  {
    path: "/plugin-easy/agiso",
    name: "PluginEasyAgiso",
    component: () => import("./views/agiso/index.vue"),
    meta: { title: "Easy 管理", icon: "ep:key" }
  }
];
export { routes };
```

### 共享依赖

主系统通过 `exposeSharedDeps()` 暴露以下全局依赖：

- `vue`、`vue-router`、`element-plus`、`pinia`
- `@shared/utils`（getAccessToken 等）

插件 `vite.config.ts` 中通过 `external` 和 `globals` 引用，不重复打包。

### 加载机制

1. 公共接口 `GET /api/plugins` 返回已安装插件的 bundle/css 路径
2. `plugin-loader.ts`（`@shared/utils/plugin-loader`）动态加载 IIFE 脚本
3. 校验 URL 必须以 `/` 开头（防止加载外部资源）

### 词典扩展

插件通过 `dictionaries` 注册字典扩展，按命名空间组织：

```typescript
window.__registerPlugin({
  name: "my-plugin",
  dictionaries: {
    funds: {
      fundPayMethodOptions: [{ label: "新支付方式", value: "new_pay" }],
      fundPayMethodMap: { new_pay: "success" }
    },
    transaction: {
      transactionTypeOptions: [{ label: "新类型", value: "new_type" }],
      transactionTypeMap: { new_type: "info" }
    }
  }
});
```

可用命名空间（admin 端全部可用，user 端不含 task、notificationRecord、notificationTemplate）：

| 命名空间 | 词典文件 | 常用可扩展字段 |
|----------|---------|---------------|
| `funds` | `views/funds/dictionary` | `fundPayMethodOptions`、`fundPayMethodMap`、`fundTypeOptions`、`fundTypeMap` |
| `transaction` | `views/transaction/dictionary` | `transactionTypeOptions`、`transactionTypeMap` |
| `order` | `views/order/dictionary` | `channelOptions`、`channel`、`channelType`、`productTypeOptions`、`productType` |
| `system` | `views/system/dictionary` | `brandOptionsAll`、`productTypeOptions`、`productTypeLabels` |
| `task` | `views/task/dictionary` | `actionLabels`、`actionTypes`、`statusLabels`、`statusTypes` |
| `invoiceLimit` | `views/invoiceLimit/dictionary` | `invoiceLimitTypeOptions`、`invoiceLimitTypeMap` |
| `notificationRecord` | `views/notification/record/dictionary` | `availableChannels`、`statusOptions` |
| `notificationTemplate` | `views/notification/template/dictionary` | `statusOptions`、`channelOptions` |

合并规则：数组用 `push` 追加，对象用 `Object.assign` 合并。

---

## 构建与发布

### 构建

```bash
# 仅构建打包（产物在 plugins/temp/）
bash plugins/build-plugin.sh easy --build-only

# 构建 + 本地发布
bash plugins/build-plugin.sh easy --local

# 构建 + 远程发布
bash plugins/build-plugin.sh easy --remote

# 远程发布到指定服务器
bash plugins/build-plugin.sh easy --remote --server cn
```

### build.json

定义哪些文件打入 zip：

```json
{
  "include": ["plugin.json", "backend/", "admin/easy-plugin.iife.js", "admin/easy-plugin-admin.css", "user/easy-plugin.iife.js", "web/", "nginx/"],
  "exclude": ["node_modules/", "src/", "*.config.ts", "package.json", "pnpm-lock.yaml"]
}
```

### 发布配置

配置文件查找优先级：`plugins/*.conf` → `build/*.conf`（回落）

### 更新地址

系统检查更新时按以下优先级获取 `releases.json`：

1. `plugin.json.release_url`（第三方插件自定义）
2. `{主系统 release_url}/plugins/{name}`（官方插件）

---

## 安装/更新/卸载

### 管理面板

系统管理 → 插件管理页面操作。

### API

| 操作 | 端点 | 参数 |
|------|------|------|
| 已安装列表 | `GET /api/admin/plugin/installed` | - |
| 检查更新 | `GET /api/admin/plugin/check-updates` | - |
| 安装 | `POST /api/admin/plugin/install` | `name`, `release_url?`, `version?` 或 `file`（上传） |
| 更新 | `POST /api/admin/plugin/update` | `name`, `version?` |
| 卸载 | `POST /api/admin/plugin/uninstall` | `name`, `remove_data?` |

### 手动安装

```bash
cd plugins && unzip easy-plugin-0.0.1.zip
cd ../backend
php artisan migrate --path=../plugins/easy/backend/migrations --force
php artisan route:clear && php artisan config:clear
```

---

## 安全机制

- autoload 使用 `realpath()` 防止路径遍历
- ZIP 解压前检查所有条目，拒绝含 `..` 的路径
- 公共端点仅返回 bundle/css 路径，管理端返回完整信息
- plugin-loader 校验 URL 必须以 `/` 开头
