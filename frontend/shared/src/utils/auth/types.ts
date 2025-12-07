export interface DataInfo<T> {
  /** token */
  access_token: string;
  /** `access_token`的过期时间（时间戳） */
  expires_in: T;
  /** 用于调用刷新access_token的接口时所需的token */
  refresh_token: string;
  /** 用户名 */
  username?: string;
  /** 余额（用户端特有） */
  balance?: string;
  /** 当前登录用户的角色 */
  roles?: Array<string>;
  /** 当前登录用户的按钮级别权限 */
  permissions?: Array<string>;
}

/** Store 钩子接口，用于依赖注入 */
export interface AuthStoreHooks {
  /** 获取是否记住登录 */
  getIsRemembered: () => boolean;
  /** 获取记住登录天数 */
  getLoginDay: () => number;
  /** 设置用户名 */
  setUsername: (username: string) => void;
  /** 设置余额（可选，用户端使用） */
  setBalance?: (balance: string) => void;
  /** 设置角色 */
  setRoles: (roles: string[]) => void;
  /** 设置权限 */
  setPerms: (permissions: string[]) => void;
  /** 获取权限列表 */
  getPermissions: () => string[];
}

/** Auth 实例接口 */
export interface AuthInstance {
  /** 用户信息 storage key */
  userKey: string;
  /** Token cookie key */
  TokenKey: string;
  /** 多标签页 cookie key */
  multipleTabsKey: string;
  /** 获取 token */
  getToken: () => DataInfo<number> | null;
  /** 设置 token */
  setToken: (data: DataInfo<Date>) => void;
  /** 删除 token */
  removeToken: () => void;
  /** 格式化 token */
  formatToken: (token: string) => string;
  /** 检查按钮级别权限 */
  hasPerms: (value: string | string[]) => boolean;
}
