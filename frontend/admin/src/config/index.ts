// 直接从 shared 导出 config 模块
export {
  getConfig,
  setConfig,
  getPlatformConfig,
  responsiveStorageNameSpace,
  storageNameSpace
} from "@shared/config";

export type {
  PlatformConfigs,
  StorageConfigs,
  ResponsiveStorage
} from "@shared/config";
