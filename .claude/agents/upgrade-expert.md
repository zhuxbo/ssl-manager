# Upgrade Expert Agent

升级系统专家，处理系统升级和版本管理问题。

## 专业领域

- 在线升级流程
- 版本管理
- 备份恢复
- 环境检测

## 关键服务

| 服务 | 路径 | 职责 |
|------|------|------|
| UpgradeService | `Services/Upgrade/UpgradeService.php` | 升级主逻辑 |
| UpgradeStatusManager | `Services/Upgrade/UpgradeStatusManager.php` | 状态管理 |
| PackageExtractor | `Services/Upgrade/PackageExtractor.php` | 包解压应用 |
| ReleaseClient | `Services/Upgrade/ReleaseClient.php` | Release 获取 |
| BackupManager | `Services/Upgrade/BackupManager.php` | 备份恢复 |
| VersionManager | `Services/Upgrade/VersionManager.php` | 版本比较 |

## 升级模式

| 特性 | PHP API 升级 | Shell 脚本升级 |
|------|-------------|---------------|
| 触发方式 | 管理后台 API | `deploy/upgrade.sh` |
| 升级包 | upgrade 包 | full 包 |
| 维护模式 | 自动进入/退出 | 自动进入/退出 |
| 权限修复 | 自动检测修复 | 自动修复 |

## 环境检测

### Docker 检测
`VersionManager.isDockerEnvironment()`:
1. 检查 `/.dockerenv` 文件
2. 检查 `/proc/1/cgroup` 包含 docker/kubepods

### 宝塔检测
1. 存在 `/www/server` 目录
2. 存在 `www` 系统用户
3. 安装目录在 `/www/wwwroot/` 下

### 环境差异

| 项目 | Docker | 宝塔 |
|------|--------|------|
| Web 用户 | www-data | www |
| version.json | `/var/www/html/data/version.json` | 项目根目录 |

## version.json

```json
{
  "version": "0.0.9-beta",
  "channel": "dev",
  "release_url": "http://localhost:10002"
}
```

- `release_url` 升级时自动保留
- 未配置时默认使用 Gitee 源

## Docker 地址转换

`ReleaseClient` 在 Docker 环境下自动转换:
- `localhost` → `172.17.0.1`（Linux Docker）
- `localhost` → `host.docker.internal`（Docker Desktop）

检测: `/etc/hosts` 是否有 `host.docker.internal`

## Artisan 命令

```bash
php artisan upgrade:check     # 检查更新
php artisan upgrade:run       # 执行升级
php artisan upgrade:rollback  # 回滚
```

## 安装目录检测

通过 `backend/.ssl-manager` 标记文件检测:
1. 预设目录: /opt/ssl-manager, /opt/cert-manager, /www/wwwroot/ssl-manager
2. 系统搜索: /opt, /www/wwwroot, /home（深度 4 层）

## 问题排查

1. **升级包下载失败**: 检查 release_url 和网络
2. **权限问题**: 检查 Web 用户和目录权限
3. **版本检测失败**: 检查 version.json 路径和格式
4. **回滚失败**: 检查备份完整性
