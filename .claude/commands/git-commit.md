# Git 提交

提交信息: $ARGUMENTS

## 执行步骤

1. **检查工作区状态**
   - 执行 `git status --short`
   - 如无改动则提示并退出

2. **代码格式化**
   - 后端: `cd backend && ./vendor/bin/pint`
   - 前端 admin: `cd frontend/admin && pnpm prettier --write "src/**/*.{ts,tsx,vue}"`
   - 前端 user: `cd frontend/user && pnpm prettier --write "src/**/*.{ts,tsx,vue}"`

3. **显示改动摘要**
   - `git diff --stat`
   - 列出将要提交的文件

4. **生成提交信息**
   - 如提供了 `$ARGUMENTS`，直接使用
   - 如未提供，根据改动内容自动生成

5. **执行提交**
   - `git add -A`
   - `git commit -m "<提交信息>"`
   - 不添加 Co-Authored-By 等额外信息

6. **显示版本信息**
   - 显示最近的 tag: `git tag -l --sort=-v:refname | head -3`
   - 提示用户可使用 `/cert-release` 发布新版本

7. **询问是否推送**
   - 提示用户是否执行 `git push`

## 使用示例

```
/git-commit feat: 添加用户认证功能
/git-commit fix: 修复登录页面样式问题
/git-commit                              # 自动生成提交信息
```

## 提交信息规范

| 前缀 | 用途 |
|------|------|
| feat | 新功能 |
| fix | 修复 bug |
| refactor | 重构代码 |
| docs | 文档更新 |
| style | 代码格式化 |
| test | 测试相关 |
| chore | 构建/工具变动 |
