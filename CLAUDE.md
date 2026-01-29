# Manager Monorepo

## 项目结构

```
frontend/
├── shared/     # 共享代码库（@shared/*）
├── admin/      # 管理端应用
├── user/       # 用户端应用
└── base/       # 上游框架（只读）
backend/        # Laravel 11 后端
build/          # 构建系统
deploy/         # 部署脚本
develop/        # 开发环境
skills/         # 开发规范（详细文档）
```

## 核心指令

- **不要自动提交** - 完成修改后等待用户确认"提交"再执行 git commit/push
- **提交前格式化** - 后端 `./vendor/bin/pint`，前端 `pnpm prettier --write`
- **base 目录只读** - 通过 git subtree 同步上游代码，不要修改
- **PHP 8.3+** - 双引号变量不加大括号（如 `"$var"` 而非 `"{$var}"`）

## 开发规范

详细规范见 `skills/SKILL.md`，按领域组织：

| Skill | 内容 |
|-------|------|
| `skills/backend-dev/` | Laravel API、ACME 协议、升级系统 |
| `skills/frontend-dev/` | Vue 3、Monorepo、共享组件 |
| `skills/deploy-ops/` | Docker、宝塔、环境配置 |
| `skills/build-release/` | 版本发布、打包、CI/CD |

## 知识积累

开发中确定的信息写入对应 skill 文件：
- 新的架构约定或设计模式
- 疑难问题的解决方案
- 文档中缺失的重要信息

## 功能特性

### 委托验证 (delegation)

- `delegation` 提交到 CA 时转换为 `txt`，通过 `dcv.is_delegate` 标记区分
- 产品同步时保留本地的 `delegation` 验证方法
- 详见 `skills/backend-dev/SKILL.md` 委托验证章节

### 自动续费/重签

- `orders.auto_renew`: 订单级自动续费开关（null 时回落到用户设置）
- `orders.auto_reissue`: 订单级自动重签开关（null 时回落到用户设置）
- `users.auto_settings`: 用户级默认设置 `{"auto_renew": false, "auto_reissue": false}`
- `AutoRenewCommand` 同时处理续费和重签，根据订单周期与证书到期时间差判断
