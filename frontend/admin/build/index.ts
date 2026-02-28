/**
 * Admin 应用的 build 入口
 * 初始化共享 build 工具并重导出
 */
import pkg from "../package.json";
import {
  createBuildUtils,
  root,
  getPackageSize
} from "../../shared/build/utils.js";

// 使用应用根目录初始化 build 工具
const appRoot = process.cwd();
const buildUtils = createBuildUtils(appRoot, pkg);

// 导出初始化后的工具
export const { pathResolve, alias, __APP_INFO__, wrapperEnv } = buildUtils;
export { root, getPackageSize };

// 重新导出共享模块
export {
  getPluginsList,
  type PluginsOptions
} from "../../shared/build/plugins.js";
export { include, exclude } from "../../shared/build/optimize.js";
export { getCdn } from "../../shared/build/cdn.js";
export { configCompressPlugin } from "../../shared/build/compress.js";
export { viteBuildInfo } from "../../shared/build/info.js";
