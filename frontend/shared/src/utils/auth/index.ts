import Cookies from "js-cookie";
import { storageLocal, isString, isIncludeAllChildren } from "@pureadmin/utils";
import { storageNameSpace } from "../../config";
import type { DataInfo, AuthStoreHooks, AuthInstance } from "./types";

export type { DataInfo, AuthStoreHooks, AuthInstance };

/** 全局 auth 实例 */
let authInstance: AuthInstance | null = null;

/** 全局 store hooks */
let storeHooks: AuthStoreHooks | null = null;

/**
 * 创建 Auth 实例
 * @param hooks Store 钩子函数
 */
export function createAuth(hooks: AuthStoreHooks): AuthInstance {
  storeHooks = hooks;

  // 从配置读取命名空间前缀
  const ns = storageNameSpace();

  // 生成 storage keys
  const userKey = `${ns}info`;
  const TokenKey = `${ns}authorized-token`;
  const multipleTabsKey = `${ns}multiple-tabs`;

  /** 获取 token */
  function getToken(): DataInfo<number> | null {
    const cookieToken = Cookies.get(TokenKey);
    return cookieToken
      ? JSON.parse(cookieToken)
      : storageLocal().getItem(userKey);
  }

  /** 设置 token */
  function setToken(data: DataInfo<Date>): void {
    if (!storeHooks) {
      console.error("[auth] Store hooks not initialized");
      return;
    }

    let expires = 0;
    const { access_token, refresh_token, expires_in } = data;
    const isRemembered = storeHooks.getIsRemembered();
    const loginDay = storeHooks.getLoginDay();

    expires = new Date(expires_in as unknown as string).getTime();

    const cookieString = JSON.stringify({
      access_token,
      expires_in,
      refresh_token
    });

    expires > 0
      ? Cookies.set(TokenKey, cookieString, {
          expires: (expires - Date.now()) / 86400000
        })
      : Cookies.set(TokenKey, cookieString);

    Cookies.set(
      multipleTabsKey,
      "true",
      isRemembered
        ? {
            expires: loginDay
          }
        : {}
    );

    function setUserKey(info: {
      username: string;
      balance?: string;
      roles: string[];
      permissions: string[];
    }) {
      storeHooks!.setUsername(info.username);
      storeHooks!.setRoles(info.roles);
      storeHooks!.setPerms(info.permissions);
      // 如果有 setBalance 方法且有 balance 值，则设置余额
      if (storeHooks!.setBalance && info.balance !== undefined) {
        storeHooks!.setBalance(info.balance);
      }

      const storageData: any = {
        refresh_token,
        expires_in,
        username: info.username,
        roles: info.roles,
        permissions: info.permissions
      };
      // 如果有 balance，也存储
      if (info.balance !== undefined) {
        storageData.balance = info.balance;
      }
      storageLocal().setItem(userKey, storageData);
    }

    const storedInfo = storageLocal().getItem<DataInfo<number>>(userKey);
    const username = data.username ?? storedInfo?.username ?? "";
    const balance = data.balance ?? storedInfo?.balance ?? "0.00";
    const roles = data.roles ?? storedInfo?.roles ?? [];
    const permissions = data.permissions ?? storedInfo?.permissions ?? [];

    setUserKey({
      username,
      balance,
      roles,
      permissions
    });
  }

  /** 删除 token */
  function removeToken(): void {
    Cookies.remove(TokenKey);
    Cookies.remove(multipleTabsKey);
    storageLocal().removeItem(userKey);
  }

  /** 格式化 token（jwt格式） */
  function formatToken(token: string): string {
    return "Bearer " + token;
  }

  /** 是否有按钮级别的权限 */
  function hasPerms(value: string | string[]): boolean {
    if (!value) return false;
    if (!storeHooks) return false;

    const allPerms = "*:*:*";
    const permissions = storeHooks.getPermissions();

    if (!permissions) return false;
    if (permissions.length === 1 && permissions[0] === allPerms) return true;

    const isAuths = isString(value)
      ? permissions.includes(value)
      : isIncludeAllChildren(value, permissions);

    return isAuths ? true : false;
  }

  authInstance = {
    userKey,
    TokenKey,
    multipleTabsKey,
    getToken,
    setToken,
    removeToken,
    formatToken,
    hasPerms
  };

  return authInstance;
}

/**
 * 获取当前 Auth 实例
 * @throws 如果未初始化则抛出错误
 */
export function getAuthInstance(): AuthInstance {
  if (!authInstance) {
    throw new Error(
      "[auth] Auth not initialized. Please call createAuth() first."
    );
  }
  return authInstance;
}

/**
 * 获取 token（便捷方法）
 */
export function getToken(): DataInfo<number> | null {
  return getAuthInstance().getToken();
}

/**
 * 设置 token（便捷方法）
 */
export function setToken(data: DataInfo<Date>): void {
  return getAuthInstance().setToken(data);
}

/**
 * 删除 token（便捷方法）
 */
export function removeToken(): void {
  return getAuthInstance().removeToken();
}

/**
 * 格式化 token（便捷方法）
 */
export function formatToken(token: string): string {
  return getAuthInstance().formatToken(token);
}

/**
 * 检查权限（便捷方法）
 */
export function hasPerms(value: string | string[]): boolean {
  return getAuthInstance().hasPerms(value);
}
