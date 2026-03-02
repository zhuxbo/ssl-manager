# 构建发布规范

## 目录结构

```
build/
├── build.sh              # 主构建脚本
├── release.sh            # 远程服务器发布
├── config.json           # 构建配置
├── build.env             # 构建环境变量
├── scripts/
│   ├── release-common.sh
│   ├── package.sh
│   ├── sync-to-production.sh
│   └── container-build.sh
├── nginx/
├── web/
└── temp/                 # 临时目录（.gitignore）
```

---

## 版本发布

### 发布流程

1. 提交代码并推送
2. 构建并发布到远程服务器：`./build/release.sh <版本号>`
   - 正式版在 main 分支发布时自动创建/更新 tag 并 push
   - 测试版无需 tag

---

## 构建命令

```bash
# 构建所有模块（默认）
bash build/build.sh

# 指定版本构建
bash build/build.sh --version 0.2.1-beta

# 构建并打包
bash build/build.sh --version 0.2.1-beta --package

# 仅构建指定模块
bash build/build.sh api
bash build/build.sh admin
bash build/build.sh user

# 指定发布通道
bash build/build.sh --channel dev

# 强制重建（忽略缓存）
bash build/build.sh --force-build

# 清空依赖缓存后构建
bash build/build.sh --clear-cache
```

> **注意**：`release.sh` 内部会自动调用 `build.sh` 构建打包，无需手动先执行 `build.sh`。

---

## 打包

### 输出文件

| 文件 | 说明 |
|------|------|
| `ssl-manager-full-{version}.zip` | 完整安装包 |
| `ssl-manager-upgrade-{version}.zip` | 升级包 |
| `ssl-manager-script-{version}.zip` | 部署脚本包 |
| `manifest.json` | 包清单 |

### 手动打包

```bash
./build/scripts/package.sh
```

---

## 版本号管理

`version.json` 不在仓库中，构建时自动生成。

### 版本获取优先级

| 场景 | 优先级 |
|------|--------|
| release.sh | 命令行参数（必须指定） |
| GitHub CI | git tag |

### 本地开发

无 `version.json` 时，PHP 返回：`version=0.0.0-beta, channel=dev`

---

## 远程发布

### 配置

```bash
cp build/release.conf.example build/release.conf
chmod 600 build/release.conf
```

配置示例：

```bash
SERVERS=(
    "cn,release-cn.example.com,22,/var/www/release,https://release-cn.example.com"
    "us,release-us.example.com,22,/var/www/release,https://release-us.example.com"
)
SSH_USER="release"
SSH_KEY="~/.ssh/release"
KEEP_VERSIONS=5
```

### 发布命令

```bash
# 发布到所有服务器（自动构建+打包+上传+更新 releases.json）
bash build/release.sh <版本号>

# 只发布到指定服务器
bash build/release.sh <版本号> --server cn

# 只上传（不重新构建，仍需版本号）
bash build/release.sh <版本号> --upload-only

# 测试连接
bash build/release.sh --test
```

> `release.sh` 完整流程：测试 SSH 连接 → 调用 `build.sh` 构建打包 → 上传 zip → 更新 `releases.json` → 部署 install.sh/upgrade.sh → 创建符号链接 → 清理旧版本

---

## version.json

构建自动生成，包含在安装/升级包中：

```json
{
  "version": "0.0.9-beta",
  "channel": "dev",
  "release_url": "https://release.example.com"
}
```

| 字段 | 说明 |
|------|------|
| version | 当前版本号 |
| channel | main（正式）或 dev（开发） |
| release_url | 自定义 release URL（升级时保留） |

---

## CI/CD

### GitHub Actions

| Workflow | 触发条件 | 功能 |
|----------|---------|------|
| release.yml | 推送 v* tag | 构建、打包、创建 Release |
| ci.yml | PR/push | 代码检查、构建测试 |

GitHub Release 仅用于代码存档，实际部署使用自建 release 服务。

---

## 定制构建

`build/custom/` 目录（不纳入版本控制）：

- `build.env` - 覆盖默认构建变量
- `config.json` - 覆盖默认配置
- `logo.png` - 自定义 Logo

---

## 快速发布指令

### 完整发布流程（推荐）

```bash
# 1. 提交代码
git add . && git commit -m "feat: 功能描述" && git push

# 2. 远程发布（构建 + 打包 + 部署到服务器）
# 正式版在 main 分支上会自动创建/更新 tag 并 push
./build/release.sh <版本号>
```

- **正式版**（不含 `-`）：在 main 分支发布时，脚本自动创建/更新 `v{版本号}` tag 并 push，无需手动操作
- **测试版**（含 `-`）：无需 tag，直接发布

### Tag 命名规范

- **必须带 `v` 前缀**：`v0.0.11-beta`、`v1.0.0`
- 不带 `v` 的 tag 应清理

---

## 数据库结构导出

`structure.json` 是主系统数据库标准结构，升级时用于校验和修复。

```bash
# 使用 Docker 容器导出（推荐，环境干净）
cd backend && php artisan db:structure --export

# 使用本地 MySQL 导出（需要数据库连接）
cd backend && php artisan db:structure --export --use-local
```

- 导出命令自动排除插件迁移（`--path=database/migrations` 限制）
- 插件表由插件自身管理，不纳入主系统 `structure.json`
- 发布前确保 `structure.json` 是最新的

## 注意事项

- **不要并行执行多个构建任务**：同时运行多个 `build.sh` 会导致资源竞争和卡死
- **内存限制**：容器限制 2GB 内存，前端构建可能因 OOM 被 kill
- **构建顺序**：后端 → 管理端 → 用户端（串行，不可并行）
- **Worktree 无 git tag**：在 worktree 中构建需显式指定 `--version`，否则版本号为 `0.0.0-dev`
