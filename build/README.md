# 构建系统

## 目录结构

```
build/
├── build.sh              # 主构建脚本
├── git-release.sh        # Git 版本发布（打 tag、push）
├── local-release.sh      # 本地发布测试
├── remote-release.sh     # 远程服务器发布
├── config.json           # 构建配置
├── build.env             # 构建环境变量
├── scripts/              # 辅助脚本
│   ├── release-common.sh       # 发布公共函数库
│   ├── package.sh              # 打包脚本
│   ├── collect-artifacts.sh    # 汇总构建产物
│   └── container-build.sh      # 容器化构建
├── nginx/                # Nginx 配置模板
├── web/                  # Web 服务配置
└── temp/                 # 构建临时目录（.gitignore）
```

## 版本发布

### Git 版本发布

```bash
# 发布测试版（自动推送到 dev 分支）
./build/git-release.sh 0.0.10-beta

# 发布正式版（自动推送到 main 分支）
./build/git-release.sh 1.0.0

# 强制重新发布（删除旧 tag 后重新创建）
./build/git-release.sh 0.0.10-beta --force

# 仅本地提交，不推送
./build/git-release.sh 0.0.10-beta --no-push
```

### 分支规则

| 版本类型 | 示例 | 目标分支 |
|---------|------|---------|
| 正式版 | 1.0.0, 2.1.0 | main |
| 测试版 | 0.0.10-beta, 1.0.0-rc.1 | dev |
| 开发版 | 0.0.10-dev, 1.0.0-alpha | dev |

### 发布流程

1. `git-release.sh` 自动更新 `version.json`
2. 提交更改并创建 tag
3. 推送到远程仓库
4. 本地/远程发布：
   - `local-release.sh` - 发布到本地测试服务
   - `remote-release.sh` - 发布到远程服务器

## 构建命令

```bash
# 构建所有模块
./build/build.sh

# 指定版本号构建
./build/build.sh --version 1.0.0

# 指定版本和通道
./build/build.sh --version 0.1.7-beta --channel dev

# 构建并创建安装包
./build/build.sh --version 1.0.0 --package

# 仅构建指定模块
./build/build.sh admin     # 仅管理端
./build/build.sh api       # 仅后端
```

## 打包

构建完成后，打包脚本会生成：

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

## 版本号管理

`version.json` 不在仓库中，由构建时自动生成。

### 版本获取优先级

| 场景 | 优先级 | 说明 |
|------|--------|------|
| **build.sh** | --version 参数 > git tag > 0.0.0-dev | 通过环境变量传入容器 |
| **remote-release.sh** | 命令行参数 > git tag | 必须明确指定，不回退 |
| **GitHub CI** | git tag | 由 tag push 触发 |
| **local-release.sh** | 命令行参数 > git tag > 默认值 | 灵活，方便本地测试 |

### 本地开发

无 `version.json` 时，PHP 返回默认值：`version=0.0.0-beta, channel=dev`

## CI/CD

### GitHub Actions

| Workflow | 触发条件 | 功能 |
|----------|---------|------|
| `release.yml` | 推送 `v*` tag | 构建、打包、创建 GitHub Release |
| `ci.yml` | PR/push | 代码检查、构建测试 |

GitHub Release 仅用于代码存档，实际部署使用自建 release 服务。

## 本地发布测试

### 配置

```bash
# 创建配置文件（必须）
cp build/local-release.conf.example build/local-release.conf
vim build/local-release.conf
```

### 环境准备

搭建本地 release 服务用于测试升级流程：

```
/www/wwwroot/dev/release/           # Release 服务目录 (https://release.example.com)
├── install.sh                      # 安装脚本（自动注入 release_url）
├── upgrade.sh                      # 升级脚本（自动注入 release_url）
├── releases.json                   # Release 索引
├── main/v1.0.0/                    # 正式版
│   └── ssl-manager-*.zip
├── dev/v0.0.10-beta/               # 开发版
│   └── ssl-manager-*.zip
├── latest/                         # 最新稳定版符号链接
└── dev-latest/                     # 最新开发版符号链接
```

### 一键发布

```bash
# 构建并发布到本地 release 服务
./build/local-release.sh              # 自动检测版本（git tag 或默认值）
./build/local-release.sh 0.0.10-beta  # 指定版本（推荐）
```

脚本会自动：
1. 构建所有包（full、upgrade、script）
2. 复制到对应版本目录
3. 更新 releases.json
4. 部署 install.sh/upgrade.sh 到根目录（替换 `__RELEASE_URL__` 占位符）
5. 创建 latest 符号链接
6. 设置权限

### 测试安装/升级

```bash
# 一键安装（curl 方式）
curl -fsSL https://release.example.com/install.sh | sudo bash

# 一键升级
curl -fsSL https://release.example.com/upgrade.sh | sudo bash

# 指定版本安装
curl -fsSL https://release.example.com/install.sh | sudo bash -s -- --version 0.0.10-beta

# 手动升级（指定目录）
./deploy/upgrade.sh --url https://release.example.com --version 0.0.10-beta --dir /path/to/app -y
```

### version.json（运行时）

`version.json` 由构建自动生成，包含在安装/升级包中：

```json
{
  "version": "0.0.9-beta",
  "channel": "dev",
  "release_url": "https://release.example.com"
}
```

| 字段 | 说明 |
|------|------|
| `version` | 当前版本号（构建时注入） |
| `channel` | 渠道：`main`（正式）或 `dev`（开发） |
| `release_url` | 自定义 release 服务 URL（可选，升级时保留） |

`release_url` 在升级过程中会被保留，不会被新版本覆盖。

## 远程发布

### 服务器设置

在远程 release 服务器上执行以下配置：

```bash
# 1. 创建 release 用户
useradd -m -s /bin/bash release

# 2. 设置 SSH 密钥登录
mkdir -p /home/release/.ssh
chmod 700 /home/release/.ssh

# 将本地公钥添加到 authorized_keys
echo "ssh-ed25519 AAAA... your-key" >> /home/release/.ssh/authorized_keys
chmod 600 /home/release/.ssh/authorized_keys
chown -R release:release /home/release/.ssh

# 3. 创建部署目录并设置权限
mkdir -p /www/wwwroot/release.example.com
chown -R release:release /www/wwwroot/release.example.com
```

### 本地配置

```bash
# 1. 创建配置文件
cp build/remote-release.conf.example build/remote-release.conf
chmod 600 build/remote-release.conf

# 2. 编辑配置
vim build/remote-release.conf
```

配置文件示例：

```bash
# 服务器列表（格式: "名称,主机,端口,目录,URL"）
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
# 发布到所有服务器（使用 version.json 版本）
./build/remote-release.sh

# 发布指定版本
./build/remote-release.sh 0.1.0

# 只发布到指定服务器
./build/remote-release.sh --server cn

# 只上传（跳过构建）
./build/remote-release.sh --upload-only

# 测试 SSH 连接
./build/remote-release.sh --test
```

### 远程目录结构

```
/var/www/release/
├── releases.json           # Release 索引
├── install.sh              # 安装脚本（已替换 RELEASE_URL）
├── upgrade.sh              # 升级脚本（已替换 RELEASE_URL）
├── main/v1.0.0/           # 正式版
│   └── ssl-manager-*.zip
├── dev/v0.0.15-beta/      # 开发版
│   └── ssl-manager-*.zip
├── latest/                 # 最新正式版符号链接
└── dev-latest/             # 最新开发版符号链接
```

### 版本清理

每个通道自动保留最新 5 个版本（可通过 `KEEP_VERSIONS` 配置）。

## 定制构建

`build/custom/` 目录（不纳入版本控制）用于存放定制资源：

- `build.env` - 覆盖默认构建变量
- `config.json` - 覆盖默认配置
- `logo.png` - 自定义 Logo
