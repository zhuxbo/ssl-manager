# Cert Manager Skills

本目录包含项目开发规范和知识库，按领域组织。

## Skill 列表

| Skill | 目录 | 触发场景 |
|-------|------|---------|
| 后端开发 | `backend-dev/` | Laravel API、ACME 协议、升级系统 |
| 前端开发 | `frontend-dev/` | Vue 3、Monorepo、共享组件 |
| 部署运维 | `deploy-ops/` | Docker、宝塔、环境配置 |
| 构建发布 | `build-release/` | 版本发布、打包、CI/CD |
| ACME E2E 测试 | `acme-e2e-test/` | Docker certbot 端到端测试（Manager + Gateway） |

## 使用方式

根据当前任务类型，读取对应 skill 获取详细规范：

```
skills/backend-dev/SKILL.md    # 后端开发任务
skills/frontend-dev/SKILL.md   # 前端开发任务
skills/deploy-ops/SKILL.md     # 部署运维任务
skills/build-release/SKILL.md  # 构建发布任务
```

## 知识积累

开发过程中遇到以下情况时，将信息写入对应 skill：

- 发现新的架构约定或设计模式
- 解决了疑难问题（记录原因和解决方案）
- 确定了最佳实践
- 发现文档中缺失的重要信息

写入规则：

- 只记录已确定且经过验证的信息
- 保持简洁，避免冗余
- 按对应领域写入正确的 skill 文件
