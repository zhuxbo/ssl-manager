# 构建系统

## 目录结构

```
build/
├── build.sh              # 主构建脚本
├── release.sh            # 版本发布脚本
├── config.json           # 构建配置
├── build.env             # 构建环境变量
├── Dockerfile.base       # 基础镜像
├── Dockerfile.build      # 构建镜像
├── scripts/              # 辅助脚本
│   ├── sync-to-production.sh   # 同步到生产目录
│   ├── package.sh              # 打包脚本
│   ├── release.sh              # Gitee Release 上传
│   └── container-build.sh      # 容器化构建
├── nginx/                # Nginx 配置模板
├── web/                  # Web 服务配置
└── temp/                 # 构建临时目录（.gitignore）
```

## 版本发布

### 快速发布

```bash
# 发布测试版（自动推送到 dev 分支）
./build/release.sh 0.0.10-beta

# 发布正式版（自动推送到 main 分支）
./build/release.sh 1.0.0

# 仅本地提交，不推送
./build/release.sh 0.0.10-beta --no-push

# 强制指定分支
./build/release.sh 1.0.0 --branch dev
```

### 分支规则

| 版本类型 | 示例 | 目标分支 |
|---------|------|---------|
| 正式版 | 1.0.0, 2.1.0 | main |
| 测试版 | 0.0.10-beta, 1.0.0-rc.1 | dev |
| 开发版 | 0.0.10-dev, 1.0.0-alpha | dev |

### 发布流程

1. `release.sh` 自动更新 `version.json`
2. 提交更改并创建 tag
3. 推送到远程仓库
4. GitHub Actions 自动触发：
   - 构建并打包
   - 创建 GitHub Release
   - 同步到 Gitee

## 构建命令

```bash
# 进入容器构建环境
./build/build.sh

# 测试构建（不推送）
./build/build.sh --test

# 生产构建（推送到生产仓库）
./build/build.sh --prod

# 仅构建指定模块
./build/build.sh --test admin     # 仅管理端
./build/build.sh --test backend   # 仅后端
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

## 版本号注入

构建时版本号获取优先级：

1. **Git Tag**：如果当前 HEAD 有 tag，使用 tag 版本
2. **version.json**：回退到 version.json 中的版本号

这意味着：
- 开发时使用 version.json 中的版本
- 正式发布时打 tag，构建自动使用 tag 版本

## CI/CD

### GitHub Actions

| Workflow | 触发条件 | 功能 |
|----------|---------|------|
| `release.yml` | 推送 `v*` tag | 构建、打包、创建 Release |
| `sync-to-gitee.yml` | push/release | 同步代码和 Release 到 Gitee |
| `ci.yml` | PR/push | 代码检查 |

### 配置

Gitee 同步配置在 `.github/workflows/sync-to-gitee.yml`：

```yaml
env:
  GITEE_OWNER: zhuxbo
  GITEE_REPO: cert-manager
```

需要在 GitHub 仓库设置 Secrets：
- `GITEE_TOKEN`: Gitee 访问令牌

## 定制构建

`build/custom/` 目录（不纳入版本控制）用于存放定制资源：

- `build.env` - 覆盖默认构建变量
- `config.json` - 覆盖默认配置
- `logo.png` - 自定义 Logo
