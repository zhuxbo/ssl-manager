# 功能开发工作流

cert-manager 功能开发工作流命令。基于 7 阶段流程，专门针对本项目定制。

## 使用方式

```
/cert-dev <功能描述>
```

## 7 阶段流程

### Phase 1: 需求理解

明确功能需求，识别关键要点：
- 功能目标是什么？
- 涉及哪些模块（前端/后端/部署）？
- 有什么约束条件？

### Phase 2: 代码探索

使用 cert-explorer agent 深入理解相关代码：
- 定位相关文件和模块
- 理解现有实现方式
- 识别可复用的代码和模式

### Phase 3: 方案设计

基于探索结果设计实现方案：
- 需要修改/新增哪些文件？
- API 设计（如涉及后端）
- 组件设计（如涉及前端）
- 数据结构变更

### Phase 4: 实现

按设计方案逐步实现：
- 后端优先（如涉及）
- 前端跟进
- 遵循项目代码规范

### Phase 5: 测试验证

验证实现正确性：
- 功能测试
- 边界情况
- 错误处理

### Phase 6: 代码审查

自查代码质量：
- 是否符合项目规范？
- 是否有安全隐患？
- 是否有性能问题？

### Phase 7: 等待提交

**重要**：完成修改后等待用户确认"提交"再执行 git commit。

## 项目规范

### 后端 (Laravel 11)
- PHP 8.3+，双引号变量不加大括号
- PSR-12 编码规范
- 统一异常处理 (ApiResponseException)

### 前端 (Vue 3)
- TypeScript
- Composition API
- 使用 @shared/* 共享组件

### Git
- 不要自动提交
- base 目录只读

## 相关资源

- 后端规范: `skills/backend-dev/SKILL.md`
- 前端规范: `skills/frontend-dev/SKILL.md`
- 部署规范: `skills/deploy-ops/SKILL.md`
- 构建规范: `skills/build-release/SKILL.md`
