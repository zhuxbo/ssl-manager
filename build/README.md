# 构建系统

## 目录结构

```
build/
├── build.sh              # 主构建脚本
├── release.sh            # 远程服务器发布
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

### 发布流程

1. 提交代码并推送
2. 构建并发布到远程服务器：`./build/release.sh <版本号>`
   - 正式版在 main 分支发布时自动创建/更新 tag 并 push
   - 测试版无需 tag

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
| **release.sh** | 命令行参数（必须指定） | 不支持从 version.json 或 git tag 回落 |
| **GitHub CI** | git tag | 由 tag push 触发 |

### 本地开发

无 `version.json` 时，PHP 返回默认值：`version=0.0.0-beta, channel=dev`

## CI/CD

### GitHub Actions

| Workflow | 触发条件 | 功能 |
|----------|---------|------|
| `release.yml` | 推送 `v*` tag | 构建、打包、创建 GitHub Release |
| `ci.yml` | PR/push | 代码检查、构建测试 |

GitHub Release 仅用于代码存档，实际部署使用自建 release 服务。

## 发布

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
cp build/release.conf.example build/release.conf
chmod 600 build/release.conf

# 2. 编辑配置
vim build/release.conf
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
# 发布指定版本（版本号必须指定）
./build/release.sh 0.1.0

# 只发布到指定服务器
./build/release.sh 0.1.0 --server cn

# 只上传（跳过构建）
./build/release.sh 0.1.0 --upload-only

# 测试 SSH 连接
./build/release.sh --test
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
