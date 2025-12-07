# Platform Config 配置说明

## 概述

`platform-config.json` 是前端管理端的核心配置文件，位于 `public/` 目录下，在应用启动时动态加载。该文件包含了系统的基础配置、主题设置、API配置和业务相关配置。

## 配置文件结构

```json
{
  "Title": "SSL",
  "BaseUrlApi": "http://localhost:5300/admin",
  "Brands": ["certum", "gogetssl", "positive", ...],
  // ... 其他配置项
}
```

## 核心配置项说明

### 🌐 BaseUrlApi 配置

**配置项**: `BaseUrlApi`  
**类型**: `string`  
**默认值**: `"http://localhost:5300/admin"`  
**说明**: 管理端API的基础URL地址

#### 用途说明

- 定义管理端所有API请求的基础地址
- 对应后端Laravel项目的管理员API路由组 (`routes/api.admin.php`)
- 支持JWT认证的管理员专用接口

#### 环境配置

```json
{
  // 开发环境
  "BaseUrlApi": "http://localhost:5300/admin",

  // 测试环境
  "BaseUrlApi": "https://test-api.example.com/admin",

  // 生产环境
  "BaseUrlApi": "https://api.example.com/admin"
}
```

#### API路由说明

管理端API遵循以下路由结构：

- 基础路径: `/admin`
- 认证方式: JWT Token (管理员专用)
- 主要接口模块:
  - `/admin/auth/*` - 管理员认证
  - `/admin/user/*` - 用户管理
  - `/admin/order/*` - 订单管理
  - `/admin/cert/*` - 证书管理
  - `/admin/setting/*` - 系统设置

### 🏢 Brands 配置

**配置项**: `Brands`  
**类型**: `string[]`  
**说明**: SSL证书CA品牌列表配置

#### 支持的CA品牌

```json
{
  "Brands": [
    "certum", // Certum
    "gogetssl", // GoGetSSL
    "positive", // Positive SSL
    "geotrust", // GeoTrust
    "digicert", // DigiCert
    "ssltrus", // SslTrus
    "trustasia" // TrustAsia
  ]
}
```

#### 品牌配置说明

| 品牌代码    | 品牌名称     | 说明                            |
| ----------- | ------------ | ------------------------------- |
| `certum`    | Certum       | 波兰CA品牌，提供多种SSL证书产品 |
| `gogetssl`  | GoGetSSL     | 知名SSL证书经销商，价格优势明显 |
| `positive`  | Positive SSL | Comodo旗下品牌，入门级证书      |
| `geotrust`  | GeoTrust     | DigiCert旗下品牌，企业级证书    |
| `digicert`  | DigiCert     | 顶级CA品牌，高端证书产品        |
| `ssltrus`   | SslTrus      | 专业SSL证书提供商               |
| `trustasia` | TrustAsia    | 亚洲地区知名CA品牌              |

#### 在系统中的应用

1. **产品管理**: 创建SSL证书产品时选择对应品牌
2. **订单处理**: 根据品牌调用相应的CA接口
3. **证书申请**: 不同品牌有不同的申请流程和验证方式
4. **界面展示**: 前端根据品牌显示相应的图标和说明

## 其他重要配置项

### 系统基础配置

```json
{
  "Title": "SSL" // 系统标题
}
```

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
  "CachingAsyncRoutes": false // 是否缓存异步路由
}
```

## 配置使用方式

### 在代码中获取配置

```typescript
import { getConfig } from "@/config";

// 获取API基础URL
const baseUrl = getConfig("BaseUrlApi");

// 获取品牌列表
const brands = getConfig("Brands");

// 获取完整配置
const config = getConfig();
```

### HTTP请求中的使用

```typescript
// 在axios配置中使用
import { getConfig } from "@/config";

const api = axios.create({
  baseURL: getConfig("BaseUrlApi"),
  timeout: 10000
});
```

## 部署注意事项

### 1. 环境区分

不同环境需要配置对应的 `BaseUrlApi`：

- 确保API地址可访问
- 注意CORS跨域配置
- HTTPS环境下API也需要HTTPS

### 2. 品牌配置

- 新增CA品牌需要同步更新后端支持
- 品牌代码需要与后端保持一致
- 品牌图标和文案需要对应更新

### 3. 配置验证

建议在应用启动时验证配置的有效性：

```typescript
// 验证必要配置项
const requiredConfigs = ["BaseUrlApi", "Brands"];
const config = getConfig();

requiredConfigs.forEach(key => {
  if (!config[key]) {
    throw new Error(`Missing required config: ${key}`);
  }
});
```

## 最佳实践

1. **环境隔离**: 不同环境使用不同的配置文件
2. **安全考虑**: 敏感信息不应放在前端配置中
3. **缓存策略**: 配置变更后需要清理浏览器缓存
4. **测试验证**: 配置变更后需要完整测试各功能模块
