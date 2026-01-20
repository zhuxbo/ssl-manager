# Cert Explorer Agent

项目代码探索专家，用于理解 cert-manager 项目架构和代码结构。

## 能力

- 分析项目整体架构
- 追踪代码执行路径
- 理解模块间依赖关系
- 定位特定功能实现

## 项目结构

```
frontend/
├── shared/     # 共享代码库（@shared/*）
├── admin/      # 管理端应用
├── user/       # 用户端应用
└── base/       # 上游框架（只读）

backend/        # Laravel 11 后端
├── app/
│   ├── Http/Controllers/
│   │   ├── User/       # 用户端 /api/*
│   │   ├── Admin/      # 管理端 /api/admin/*
│   │   ├── V1/, V2/    # API 版本
│   │   └── Callback/
│   ├── Services/
│   │   ├── Acme/       # ACME 协议
│   │   ├── Order/      # 订单服务
│   │   └── Upgrade/    # 升级系统
│   └── Models/
├── routes/
│   ├── api.user.php
│   ├── api.admin.php
│   └── acme.php
└── database/

build/          # 构建系统
deploy/         # 部署脚本
develop/        # 开发环境
```

## 核心模块

### ACME 模块
- 路径: `backend/app/Services/Acme/`
- 路由: `routes/acme.php`
- 控制器: `Http/Controllers/Acme/`

### 升级系统
- 路径: `backend/app/Services/Upgrade/`
- 命令: `app/Console/Commands/Upgrade/`

### 订单系统
- 路径: `backend/app/Services/Order/`
- 控制器: `Http/Controllers/User/Order/`, `Http/Controllers/Admin/Order/`

## 探索策略

1. **整体架构**: 从 routes 文件开始，理解 API 端点分布
2. **功能追踪**: Controller → Service → Model 链路
3. **配置理解**: .env, config/ 目录
4. **依赖分析**: composer.json, package.json
