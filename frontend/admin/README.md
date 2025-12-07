# SSL证书管理系统 - 前端管理端

[![license](https://img.shields.io/github/license/pure-admin/vue-pure-admin.svg)](LICENSE)

## 项目介绍

这是一个基于 Vue 3 + TypeScript 开发的SSL证书管理系统前端管理端项目。系统为管理员提供了完整的SSL证书申请、签发、管理和用户管理功能，采用现代化的Web技术栈构建，具有良好的用户体验和开发体验。

项目基于 [vue-pure-admin](https://github.com/pure-admin/vue-pure-admin) 精简版进行开发，专门针对SSL证书管理业务场景进行了深度定制。

## 技术栈

- **前端框架**: Vue 3 + TypeScript
- **UI组件库**: Element Plus
- **状态管理**: Pinia
- **路由管理**: Vue Router
- **HTTP客户端**: Axios
- **构建工具**: Vite
- **样式方案**: Sass + TailwindCSS
- **代码规范**: ESLint + Prettier + Stylelint
- **包管理器**: pnpm
- **Monorepo**: pnpm workspace

## Monorepo 架构

本项目是 monorepo 的一部分，与 `user` 应用共享 `shared` 包中的代码：

```
frontend/
├── shared/     # 共享代码库
├── admin/      # 管理端应用 (本项目)
└── user/       # 用户端应用
```

### 共享包使用

通过 `@shared/*` 别名访问共享代码：

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

### 初始化配置

在 `src/utils/setup.ts` 中配置 shared 模块的依赖注入：

```typescript
import { createAuth, createHttp } from "@shared/utils";
import { setHasAuth } from "@shared/directives/auth";

// 初始化 Auth、Http 和权限指令
```

## 核心功能模块

### 🔐 认证与权限

- 管理员JWT认证
- 基于角色的权限控制
- 多级权限管理

### 👥 用户管理

- 用户账号管理
- 用户等级设置
- 用户资金管理
- 操作日志记录

### 📜 证书管理

- SSL证书申请处理
- 证书状态管理
- 证书链配置
- 免费证书配额管理
- 多CA品牌支持 (Certum、GoGetSSL、Positive、SslTrus、Sectigo等)

### 📦 订单管理

- 订单创建与审核
- 证书申请流程管理
- 批量申请处理
- 订单状态跟踪

### 💰 交易管理

- 交易记录查询
- 资金流水管理
- 发票管理
- 发票限额设置

### 🛠️ 系统管理

- 产品配置管理
- 产品价格设置
- 系统参数配置
- API Token管理
- 任务管理
- 回调配置

### 📊 日志监控

- API调用日志
- 用户操作日志
- 管理员操作日志
- CA接口日志
- 错误日志记录

## 项目结构

```txt
src/
├── api/                 # API接口定义
│   ├── auth.ts         # 认证相关
│   ├── cert.ts         # 证书管理
│   ├── order.ts        # 订单管理
│   ├── user.ts         # 用户管理
│   └── ...
├── assets/             # 静态资源
├── components/         # 公共组件
├── config/             # 配置文件
├── directives/         # 自定义指令
├── layout/             # 布局组件
├── plugins/            # 插件配置
├── router/             # 路由配置
├── store/              # 状态管理
├── style/              # 全局样式
├── utils/              # 工具函数
├── views/              # 页面组件
│   ├── admin/          # 管理员管理
│   ├── cert/           # 证书管理
│   ├── order/          # 订单管理
│   ├── user/           # 用户管理
│   ├── transaction/    # 交易管理
│   ├── setting/        # 系统设置
│   └── ...
├── App.vue             # 根组件
└── main.ts             # 应用入口
```

## 开发指南

### 环境要求

- Node.js >= 18.18.0
- pnpm >= 9.0.0

### 安装依赖

```bash
# 在 monorepo 根目录运行
pnpm install
```

### 开发模式

```bash
# 在 monorepo 根目录运行
pnpm dev:admin

# 或同时启动 admin 和 user
pnpm dev

# 或在当前目录运行
pnpm dev
```

### 构建生产版本

```bash
# 在 monorepo 根目录运行
pnpm build:admin

# 或在当前目录运行
pnpm build
```

### 代码规范检查

```bash
# ESLint检查
pnpm lint:eslint

# Prettier格式化
pnpm lint:prettier

# Stylelint样式检查
pnpm lint:stylelint

# 全部检查
pnpm lint
```

### 类型检查

```bash
pnpm typecheck
```

## 配置说明

### Platform Config

项目使用 `public/platform-config.json` 进行核心配置，详细说明请参考 [platform-config.md](./platform-config.md)。

#### 主要配置项

```json
{
  "BaseUrlApi": "http://localhost:5300/admin",
  "Brands": [
    "certum",
    "gogetssl",
    "positive",
    "geotrust",
    "digicert",
    "ssltrus",
    "trustasia"
  ]
}
```

#### 核心配置说明

- **BaseUrlApi**: 管理端API基础地址，对应后端 `routes/api.admin.php`
- **Brands**: 支持的SSL证书CA品牌列表，包含全部7个品牌
- **Version**: 系统版本号

## 开发规范

### 代码组织

- 按功能模块组织代码结构
- 使用TypeScript增强类型安全
- 遵循Vue 3 Composition API最佳实践
- 组件采用单文件组件(.vue)格式

### API接口

- 统一的HTTP请求封装
- 请求响应拦截器处理
- 错误统一处理
- 支持请求取消

### 状态管理

- 使用Pinia进行状态管理
- 按模块划分store
- 支持持久化存储

### 样式规范

- 使用Sass预处理器
- 结合TailwindCSS工具类
- 响应式设计支持
- 主题定制化

## 部署说明

### Docker部署

```bash
# 构建镜像
docker build -t ssl-user-frontend .

# 运行容器
docker run -p 80:80 ssl-user-frontend
```

### Nginx配置

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/dist;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }
}
```

## 相关链接

- [后端API文档](../../../routes/)
- [Vue 3 官方文档](https://vuejs.org/)
- [Element Plus 组件库](https://element-plus.org/)
- [Pinia 状态管理](https://pinia.vuejs.org/)

## 版本说明

当前版本基于 vue-pure-admin 精简版开发，专门为SSL证书管理系统定制。

## 许可证

[MIT © 2020-present, pure-admin](./LICENSE)
