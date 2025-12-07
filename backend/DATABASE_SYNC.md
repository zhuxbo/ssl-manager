# 数据库结构同步

## 功能说明

`db:sync-structure` 命令用于同步数据库结构与迁移文件，自动检测并修复以下差异：

- **添加缺失的表** - 根据迁移文件创建数据库中不存在的表
- **添加缺失的字段** - 向现有表添加缺失的字段
- **修改字段类型** - 修改字段的数据类型、长度、可空性等属性
- **调整索引差异** - 同步索引结构与迁移文件保持一致
- **多连接支持** - 同时检查并同步主数据库和日志数据库连接

> **注意**: 此命令仅会**添加**缺失的表和字段，**不会删除**任何现有的表或字段，确保数据安全。

## 使用场景

- ⚠️ **全新安装无需此操作** - 新安装的系统通过 `php artisan migrate` 即可
- ✅ **系统升级时** - 当升级到新版本后，用于同步数据库结构变更
- ✅ **结构检查** - 定期检查数据库结构是否与迁移文件一致

## 基本用法

### 1. 检查差异（推荐先执行）

```bash
php artisan db:sync-structure --dry-run
```

### 2. 交互式同步

```bash
php artisan db:sync-structure
```

### 3. 强制同步（无需确认）

```bash
php artisan db:sync-structure --force
```

### 4. 使用指定的临时数据库

```bash
# 预先创建临时数据库
CREATE DATABASE temp_sync_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# 使用指定的临时数据库进行同步
php artisan db:sync-structure --temp-db-name=temp_sync_db
```

## 工作原理

1. **创建临时数据库** - 临时创建一个干净的数据库
    - 优先使用默认 MySQL 配置
    - 如果权限不足，自动尝试使用临时 MySQL 配置
2. **配置临时连接** - 为主数据库和日志数据库分别配置临时连接
3. **运行迁移** - 在临时数据库中执行所有迁移文件（包括日志表）
4. **结构对比** - 分别比较主数据库和日志数据库与临时数据库的结构差异
    - 缺失的表和字段
    - 字段类型、长度、可空性等属性差异
    - 索引结构差异
5. **生成修复方案** - 生成需要执行的同步操作
6. **执行同步** - 在相应的数据库连接中执行同步操作
    - 创建缺失的表
    - 添加缺失的字段
    - 修改字段类型
    - 调整索引结构
7. **清理临时数据库** - 删除临时数据库

## 安全特性

- ✅ **只增不减** - 仅添加缺失内容，不删除现有数据
- ✅ **DDL 原子性** - 每个操作都是原子性的数据库 DDL 操作
- ✅ **预检查模式** - 支持 `--dry-run` 预先查看需要执行的操作
- ✅ **自动清理** - 自动清理临时数据库，不留痕迹

## 输出示例

### 正常情况

```bash
$ php artisan db:sync-structure --dry-run

🔍 开始同步数据库结构与迁移文件...
📝 创建临时数据库: cnssl_temp_1735469234
🔄 在临时数据库运行迁移...
✅ 迁移执行完成
🔍 比较数据库结构差异...
⚠️  主数据库表 'domain_validation_records' 不存在
⚠️  主数据库表 'orders' 缺失字段 'validation_status'
⚠️  主数据库表 'users' 字段 'email' 类型需要调整
⚠️  日志数据库表 'error_logs' 缺失字段 'user_id'
⚠️  日志数据库表 'api_logs' 字段 'url' 类型需要调整

🔍 检测到以下需要同步的差异:
==========================================
📊 主数据库:
   📋 缺失的表:
      • domain_validation_records
   🔧 缺失的字段:
      • orders.validation_status
   🔄 需要修改的字段:
      • users.email (varchar(255) → varchar(320))

📊 日志数据库:
   🔧 缺失的字段:
      • error_logs.user_id
   🔄 需要修改的字段:
      • api_logs.url (varchar(500) → varchar(2000))

总计发现 5 个差异需要同步。
🔍 仅检查模式，不执行修复操作。
🗑️  已清理临时数据库: cnssl_temp_1735469234
```

### 权限不足时自动使用临时配置

```bash
$ php artisan db:sync-structure --dry-run

🔍 开始同步数据库结构与迁移文件...
📝 创建临时数据库: cnssl_temp_1735469234
⚠️  使用默认MySQL配置创建临时数据库失败，尝试使用临时MySQL配置...
📝 使用临时MySQL配置: root@localhost
🔄 在临时数据库运行迁移...
✅ 迁移执行完成
🔍 比较数据库结构差异...
✅ 数据库结构已同步，无需修复。
🗑️  已清理临时数据库: cnssl_temp_1735469234
```

### 执行同步操作

```bash
$ php artisan db:sync-structure --force

🔍 开始同步数据库结构与迁移文件...
📝 创建临时数据库: cnssl_temp_1735469234
🔄 在临时数据库运行迁移...
✅ 迁移执行完成
🔍 比较数据库结构差异...
⚠️  主数据库表 'api_logs' 字段 'url' 类型需要调整

🔍 检测到以下需要同步的差异:
==========================================
📊 主数据库:
   🔄 需要修改的字段:
      • api_logs.url (varchar(500) → varchar(2000))

总计发现 1 个差异需要同步。
🔧 开始执行同步操作...

🔧 同步主数据库字段类型...
✅ 修改字段 api_logs.url (varchar(500) → varchar(2000)) 成功

✅ 所有 1 个同步操作执行完成！
🗑️  已清理临时数据库: cnssl_temp_1735469234
```

## 权限要求

命令需要以下数据库权限：

- **CREATE** - 创建临时数据库
- **DROP** - 删除临时数据库
- **ALTER** - 修改表结构和索引
- **SELECT** - 查询表结构信息

### 权限不足的解决方案

如果遇到 "❌ 数据库权限不足，无法创建临时数据库！" 错误：

#### 方案一：授予权限（推荐）

```bash
# 使用具有管理权限的账号执行
GRANT CREATE ON *.* TO 'your_username'@'%';
FLUSH PRIVILEGES;
```

#### 方案二：使用高权限账号

```bash
# 临时修改 .env 文件使用 root 账号
DB_USERNAME=root
DB_PASSWORD=your_root_password
```

#### 方案三：使用指定的临时数据库名称

```bash
# 预先创建一个固定名称的临时数据库
CREATE DATABASE `temp_sync_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# 使用 --temp-db-name 参数指定临时数据库
php artisan db:sync-structure --temp-db-name=temp_sync_db
```

#### 方案四：使用临时 MySQL 配置（新增）

在 `.env` 文件中添加临时 MySQL 配置：

```bash
# 临时MySQL配置（通常是高权限账号）
DB_TEMP_HOST=localhost
DB_TEMP_PORT=3306
DB_TEMP_DATABASE=your_database
DB_TEMP_USERNAME=root
DB_TEMP_PASSWORD=your_root_password
```

配置后，当默认 MySQL 配置权限不足时，系统会自动尝试使用临时 MySQL 配置。

## 注意事项

- 建议在生产环境执行前先备份数据库
- 大型数据库的同步操作可能需要较长时间
- 确保数据库用户有足够的权限执行 DDL 操作
- 默认临时数据库名称格式：`原数据库名_temp_时间戳`
- 使用 `--temp-db-name` 参数可以指定固定的临时数据库名称
- 用户指定的临时数据库在命令执行完成后不会被自动删除

### 字段类型修改说明

- **支持的修改类型**：数据类型、字段长度、可空性、默认值、注释等
- **常见场景**：`varchar(255)` → `varchar(500)`、`int(11)` → `bigint(20)` 等
- **安全性**：扩展字段长度通常是安全的，缩小长度时请确保数据不会截断
- **性能影响**：大表的字段类型修改可能需要较长时间，建议在维护窗口执行
