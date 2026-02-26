# 构建发布规范

## 目录结构

```
build/
├── build.sh              # 主构建脚本
├── git-release.sh        # Git 版本发布
├── local-release.sh      # 本地发布测试
├── remote-release.sh     # 远程服务器发布
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

### Git 版本发布

```bash
# 发布测试版（推送到 dev 分支）
./build/git-release.sh 0.0.10-beta

# 发布正式版（推送到 main 分支）
./build/git-release.sh 1.0.0

# 强制重新发布
./build/git-release.sh 0.0.10-beta --force

# 仅本地提交
./build/git-release.sh 0.0.10-beta --no-push
```

### 分支规则

| 版本类型 | 示例 | 目标分支 |
|---------|------|---------|
| 正式版 | 1.0.0, 2.1.0 | main |
| 测试版 | 0.0.10-beta, 1.0.0-rc.1 | dev |
| 开发版 | 0.0.10-dev, 1.0.0-alpha | dev |

### 发布流程

1. `git-release.sh` 更新 `version.json`
2. 提交更改并创建 tag
3. 推送到远程仓库
4. 执行本地/远程发布

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

> **注意**：`remote-release.sh` 内部会自动调用 `build.sh` 构建打包，无需手动先执行 `build.sh`。

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
| remote-release.sh | 命令行参数 > git tag（必须明确） |
| GitHub CI | git tag |
| local-release.sh | 命令行参数 > git tag > 默认值 |

### 本地开发

无 `version.json` 时，PHP 返回：`version=0.0.0-beta, channel=dev`

---

## 本地发布测试

### 配置

```bash
cp build/local-release.conf.example build/local-release.conf
vim build/local-release.conf
```

### Release 服务目录

```
/www/wwwroot/dev/release/
├── install.sh            # 安装脚本（注入 release_url）
├── upgrade.sh            # 升级脚本（注入 release_url）
├── releases.json         # Release 索引
├── main/v1.0.0/         # 正式版
├── dev/v0.0.10-beta/    # 开发版
├── latest/               # 最新稳定版符号链接
└── dev-latest/           # 最新开发版符号链接
```

### 一键发布

```bash
# 自动检测版本
./build/local-release.sh

# 指定版本（推荐）
./build/local-release.sh 0.0.10-beta
```

脚本自动：
1. 构建所有包（full、upgrade、script）
2. 复制到版本目录
3. 更新 releases.json
4. 部署 install.sh/upgrade.sh（替换占位符）
5. 创建 latest 符号链接

### 测试安装/升级

```bash
# 一键安装
curl -fsSL https://release.example.com/install.sh | sudo bash

# 指定版本
curl -fsSL https://release.example.com/install.sh | sudo bash -s -- --version 0.0.10-beta

# 手动升级
./deploy/upgrade.sh --url https://release.example.com --version 0.0.10-beta --dir /path/to/app -y
```

---

## 远程发布

### 配置

```bash
cp build/remote-release.conf.example build/remote-release.conf
chmod 600 build/remote-release.conf
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
bash build/remote-release.sh <版本号>

# 只发布到指定服务器
bash build/remote-release.sh <版本号> --server cn

# 只上传（不重新构建）
bash build/remote-release.sh --upload-only

# 测试连接
bash build/remote-release.sh --test
```

> `remote-release.sh` 完整流程：测试 SSH 连接 → 调用 `build.sh` 构建打包 → 上传 zip → 更新 `releases.json` → 部署 install.sh/upgrade.sh → 创建符号链接 → 清理旧版本

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
# 1. 远程发布（构建 + 打包 + 部署到服务器）
./build/remote-release.sh

# 2. 提交代码
git add . && git commit -m "feat: 功能描述" && git push

# 3. 更新 tag 到最新提交
git tag -d v0.0.11-beta && git push origin :refs/tags/v0.0.11-beta && git tag v0.0.11-beta && git push origin v0.0.11-beta
```

### 仅发布（不提交）

适用于测试阶段，代码未最终确定：

```bash
./build/remote-release.sh
```

### 更新已有 tag

当需要将 tag 指向新的提交时：

```bash
# 删除本地和远程 tag，重新创建并推送
git tag -d v版本号 && git push origin :refs/tags/v版本号 && git tag v版本号 && git push origin v版本号
```

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
