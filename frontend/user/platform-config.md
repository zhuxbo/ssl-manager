# Platform Config 配置说明 - 用户端

## 概述

`platform-config.json` 是前端用户端的核心配置文件，位于 `public/` 目录下，在应用启动时动态加载。该文件包含了系统的基础配置、主题设置、API配置和用户端专用配置。

## 配置文件结构

```json
{
  "Title": "SSL",
  "BaseUrlApi": "http://localhost:5300",
  "Brands": ["certum", "gogetssl", "positive", "ssltrus", "trustasia"],
  "Beian": "豫ICP备123456789号",
  "FixedHeader": true,
  "Layout": "vertical",
  "Theme": "light"
  // ... 其他配置项
}
```

## 核心配置项说明

### 🌐 BaseUrlApi 配置

**配置项**: `BaseUrlApi`  
**类型**: `string`  
**默认值**: `"http://localhost:5300"`  
**说明**: 用户端API的基础URL地址

#### 用途说明

- 定义用户端所有API请求的基础地址
- 对应后端Laravel项目的用户API路由组 (`routes/api.user.php`)
- 支持JWT认证的用户专用接口

#### 环境配置

```json
{
  // 开发环境
  "BaseUrlApi": "http://localhost:5300",

  // 测试环境
  "BaseUrlApi": "https://test-api.example.com",

  // 生产环境
  "BaseUrlApi": "https://api.example.com"
}
```

#### API路由说明

用户端API采用以下路由结构：

- 基础路径: `/`（无前缀）
- 认证方式: JWT Token (用户专用)
- 主要接口模块:
  - `/auth/*` - 用户认证
  - `/order/*` - 订单管理
  - `/cert/*` - 证书查看
  - `/product/*` - 产品浏览
  - `/funds/*` - 资金管理

### 🏢 Brands 配置

**配置项**: `Brands`  
**类型**: `string[]`  
**说明**: 用户端支持的SSL证书CA品牌列表

#### 支持的CA品牌

```json
{
  "Brands": [
    "certum", // Certum
    "gogetssl", // GoGetSSL
    "positive", // Positive SSL
    "ssltrus", // SslTrus
    "trustasia" // TrustAsia
  ]
}
```

#### 品牌说明

| 品牌代码    | 品牌名称     | 特点                 |
| ----------- | ------------ | -------------------- |
| `certum`    | Certum       | 波兰CA品牌，性价比高 |
| `gogetssl`  | GoGetSSL     | 知名经销商，价格优势 |
| `positive`  | Positive SSL | 入门级证书，适合个人 |
| `ssltrus`   | SslTrus      | 专业SSL证书提供商    |
| `trustasia` | TrustAsia    | 亚洲本土化服务       |

#### 在用户端的应用

1. **产品选择**: 用户浏览证书产品时按品牌分类显示
2. **订单创建**: 根据选择的产品确定对应的CA品牌
3. **证书申请**: 不同品牌有不同的申请流程和验证方式
4. **界面展示**: 前端根据品牌显示相应的图标和说明

### 🏛️ Beian 配置

**配置项**: `Beian`  
**类型**: `string`  
**默认值**: `"豫ICP备123456789号"`  
**说明**: 网站备案号显示

#### 用途说明

- 在用户端页面底部显示备案信息
- 符合中国大陆网站备案要求
- 提供合规的网站身份信息

#### 配置示例

```json
{
  "Beian": "京ICP备12345678号-1"
}
```

## 系统配置项

### 界面主题配置

```json
{
  "Layout": "vertical", // 布局方式：vertical/horizontal
  "Theme": "light", // 主题：light/dark
  "EpThemeColor": "#409EFF", // Element Plus主题色
  "ShowLogo": true, // 是否显示Logo
  "FixedHeader": true // 是否固定头部
}
```

### 功能开关配置

```json
{
  "KeepAlive": true, // 是否启用页面缓存
  "MultiTagsCache": true, // 是否启用多标签缓存
  "HiddenSideBar": false, // 是否隐藏侧边栏
  "HideFooter": false, // 是否隐藏页脚
  "MenuSearchHistory": 6 // 菜单搜索历史数量
}
```

### 系统基础配置

```json
{
  "Title": "SSL", // 系统标题
  "TooltipEffect": "light" // 提示框效果
}
```

## 配置使用方式

### 在代码中获取配置

```typescript
import { getConfig } from "@/config";

// 获取API基础URL
const baseUrl = getConfig("BaseUrlApi"); // "http://localhost:5300"

// 获取备案号
const beian = getConfig("Beian"); // "豫ICP备123456789号"

// 获取品牌列表
const brands = getConfig("Brands");

// 获取主题配置
const theme = getConfig("Theme"); // "light"
```

### HTTP请求中的使用

```typescript
// axios配置
import { getConfig } from "@/config";

const api = axios.create({
  baseURL: getConfig("BaseUrlApi"), // 用户端API地址
  timeout: 10000
});

// 请求示例
// GET http://localhost:5300/order/list
// GET http://localhost:5300/product/list
```

## 环境配置管理

### 开发环境配置

```json
{
  "BaseUrlApi": "http://localhost:5300",
  "Beian": "豫ICP备123456789号",
  "Theme": "light",
  "ShowLogo": true
}
```

### 生产环境配置

```json
{
  "BaseUrlApi": "https://api.yourdomain.com",
  "Beian": "实际备案号",
  "Theme": "light",
  "ShowLogo": true
}
```

## 部署注意事项

### 1. API地址配置

- **开发环境**: 指向本地后端服务
- **生产环境**: 指向线上API域名
- **HTTPS**: 生产环境建议使用HTTPS

### 2. 备案信息

- 根据实际情况填写正确的备案号
- 海外部署可以移除此配置项
- 备案号格式需符合相关规范

### 3. 品牌支持

- 确保配置的品牌后端都支持
- 新增品牌需要前后端同步更新
- 品牌代码需要与后端保持一致

### 4. 存储配置

- 不同环境可以使用不同的命名空间
- 避免开发和生产数据混淆
- 定期清理无用的存储数据

## 配置验证

建议在应用启动时验证配置的有效性：

```typescript
// 验证必要配置项
const requiredConfigs = ["BaseUrlApi", "Brands", "ResponsiveStorageNameSpace"];
const config = getConfig();

requiredConfigs.forEach(key => {
  if (!config[key]) {
    throw new Error(`Missing required config: ${key}`);
  }
});

// 验证API连接
if (config.BaseUrlApi) {
  // 测试API连接性
  fetch(`${config.BaseUrlApi}/health-check`)
    .then(() => console.log("API connection verified"))
    .catch(() => console.warn("API connection failed"));
}
```

## 最佳实践

1. **环境隔离**: 不同环境使用对应的配置文件
2. **版本管理**: 配置变更需要记录在版本说明中
3. **安全考虑**: 敏感信息不应放在前端配置中
4. **缓存策略**: 配置变更后需要清理浏览器缓存
5. **用户体验**: 确保备案信息准确显示
6. **品牌一致性**: 与后端支持的品牌保持同步
7. **测试验证**: 配置变更后需要完整测试用户功能
8. **文档维护**: 配置项变更及时更新文档说明
