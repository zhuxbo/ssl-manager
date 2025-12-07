/**
 * 初始化 shared 模块（auth 和 http）
 * 需要在 getPlatformConfig 之后、使用 auth/http 之前调用
 */
import { createAuth, createHttp } from "@shared/utils";
import { setHasAuth } from "@shared/directives/auth";
import { setHasAuthForAuth, setEpThemeColorGetter } from "@shared/components";
import { useUserStoreHook } from "@/store/modules/user";
import { useEpThemeStoreHook } from "@/store/modules/epTheme";
import { hasAuth } from "@/router/utils";

let initialized = false;

export function setupSharedModules() {
  if (initialized) return;

  // 初始化 Auth（user 端多了 setBalance）
  createAuth({
    getIsRemembered: () => useUserStoreHook().isRemembered,
    getLoginDay: () => useUserStoreHook().loginDay,
    setUsername: (username: string) => useUserStoreHook().SET_USERNAME(username),
    setBalance: (balance: string) => useUserStoreHook().SET_BALANCE(balance),
    setRoles: (roles: string[]) => useUserStoreHook().SET_ROLES(roles),
    setPerms: (permissions: string[]) => useUserStoreHook().SET_PERMS(permissions),
    getPermissions: () => useUserStoreHook().permissions
  });

  // 初始化 Http
  createHttp({
    refreshToken: (data: { refresh_token: string }) =>
      useUserStoreHook().handRefreshToken(data),
    logout: () => useUserStoreHook().logOut()
  });

  // 初始化 hasAuth 指令
  setHasAuth(hasAuth);

  // 初始化 ReAuth 组件
  setHasAuthForAuth(hasAuth);

  // 初始化 RePureTableBar 组件
  setEpThemeColorGetter(() => useEpThemeStoreHook().epThemeColor);

  initialized = true;
}
