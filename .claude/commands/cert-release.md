# 发布 Release 服务

输入版本号: $ARGUMENTS

## 版本号处理

- 自动添加 `v` 前缀（如 `0.0.13-beta` → `v0.0.13-beta`）
- 验证格式符合 `vX.Y.Z` 或 `vX.Y.Z-beta`
- 允许重复发布同一版本（会覆盖）

## 执行步骤

1. **验证版本号**
   - 处理 v 前缀
   - 检查格式是否正确
   - 未提供则提示输入

2. **执行本地发布**
   - `bash build/local-release.sh <版本号>`
   - 失败则停止

3. **执行远程发布**
   - `bash build/remote-release.sh <版本号>`

## 使用示例

```
/cert-release 0.0.13-beta     # 自动变为 v0.0.13-beta
/cert-release 1.0.0           # 自动变为 v1.0.0
/cert-release 0.0.12-beta     # 可重复发布，覆盖已有版本
```

## 注意事项

- 发布前请先使用 `/git-commit` 提交改动
- 确保已登录 GitHub CLI (`gh auth status`)
- 构建失败时停止发布流程
