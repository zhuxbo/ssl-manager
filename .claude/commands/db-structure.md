# 数据库结构导出

在 `backend/` 目录下执行 `sudo php artisan db:structure --export`，重新生成 `database/structure.json`。

## 执行步骤

1. **运行导出命令**
   - `cd /www/wwwroot/dev/cert-manager/backend && sudo php artisan db:structure --export`
   - 超时设置 5 分钟（命令会启动 Docker MySQL 容器，执行全部迁移后导出）
   - 如果失败，检查 Docker 容器日志：`sudo docker logs laravel-mysql-temp`

2. **验证结果**
   - 确认 `database/structure.json` 已更新（检查 `generated_at` 时间戳）

3. **清理残留容器**（仅在命令异常中断时）
   - `sudo docker rm -f laravel-mysql-temp`
