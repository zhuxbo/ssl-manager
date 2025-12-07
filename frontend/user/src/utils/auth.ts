/**
 * Auth 模块包装器
 * 提供与原 API 兼容的接口，内部使用 shared 的实现
 */
import {
  getToken as sharedGetToken,
  setToken as sharedSetToken,
  removeToken as sharedRemoveToken,
  formatToken as sharedFormatToken,
  hasPerms as sharedHasPerms,
  getAuthInstance,
  type DataInfo
} from "@shared/utils";
import { storageNameSpace } from "@/config";

export type { DataInfo };

// 动态获取 storage keys（需要在 config 加载后才能正确获取）
export const getUserKey = () => {
  try {
    return getAuthInstance().userKey;
  } catch {
    // 如果 auth 还没初始化，返回默认值
    const ns = storageNameSpace() || "";
    return `${ns}info`;
  }
};

export const getTokenKey = () => {
  try {
    return getAuthInstance().TokenKey;
  } catch {
    const ns = storageNameSpace() || "";
    return `${ns}authorized-token`;
  }
};

export const getMultipleTabsKey = () => {
  try {
    return getAuthInstance().multipleTabsKey;
  } catch {
    const ns = storageNameSpace() || "";
    return `${ns}multiple-tabs`;
  }
};

// 为了向后兼容，提供静态变量
export const userKey = "user-info";
export const TokenKey = "authorized-token";
export const multipleTabsKey = "multiple-tabs";

// 重新导出 shared 的函数
export function getToken(): DataInfo<number> | null {
  return sharedGetToken();
}

export function setToken(data: DataInfo<Date>): void {
  return sharedSetToken(data);
}

export function removeToken(): void {
  return sharedRemoveToken();
}

export const formatToken = sharedFormatToken;
export const hasPerms = sharedHasPerms;
