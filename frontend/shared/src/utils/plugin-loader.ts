import type { Router } from "vue-router";

interface PluginRouteConfig {
  parent?: string;
  route: any;
}

interface PluginConfig {
  name: string;
  routes?: PluginRouteConfig[];
  dictionaries?: Record<string, Record<string, any>>;
}

declare global {
  interface Window {
    __deps: Record<string, any>;
    __registered_plugins: PluginConfig[];
    __registerPlugin: (config: PluginConfig) => void;
  }
}

/**
 * 初始化插件注册机制，在 main.ts 中尽早调用
 */
export function initPluginSystem() {
  window.__registered_plugins = [];
  window.__registerPlugin = (config: PluginConfig) => {
    window.__registered_plugins.push(config);
  };
}

/**
 * 暴露共享依赖到 window
 */
export async function exposeSharedDeps() {
  const [Vue, VueRouter, ElementPlus, Pinia] = await Promise.all([
    import("vue"),
    import("vue-router"),
    import("element-plus"),
    import("pinia")
  ]);

  window.__deps = {
    Vue,
    VueRouter,
    ElementPlus,
    Pinia,
    getAccessToken
  };
}

/**
 * 从 Cookie 中获取 access token
 */
function getAccessToken(): string {
  const cookies = document.cookie.split(";");
  for (const cookie of cookies) {
    const [name, value] = cookie.trim().split("=");
    if (name && name.endsWith("authorized-token") && value) {
      try {
        const data = JSON.parse(decodeURIComponent(value));
        return data?.access_token || "";
      } catch {
        return "";
      }
    }
  }
  return "";
}

/**
 * 加载插件（统一 admin/user）
 * @param menuRoutes 菜单树数组（constantMenus），传入后会将插件路由同步到菜单
 */
export async function loadPlugins(
  router: Router,
  side: "admin" | "user",
  menuRoutes?: any[]
) {
  let plugins: any[];
  try {
    const resp = await fetch("/api/plugins");
    if (!resp.ok) {
      console.warn("[PluginLoader] Plugin API returned", resp.status);
      return;
    }
    const json = await resp.json();
    plugins = json?.data?.plugins ?? [];
  } catch (e) {
    console.warn("[PluginLoader] Failed to fetch plugin list:", e);
    return;
  }

  for (const plugin of plugins) {
    const name = plugin.name ?? "unknown";
    const bundleInfo = plugin[side];
    if (!bundleInfo?.bundle) continue;

    try {
      if (bundleInfo.css) {
        loadCSS(bundleInfo.css);
      }
      await loadScript(bundleInfo.bundle);
    } catch (e) {
      console.warn(`[PluginLoader] Failed to load plugin "${name}":`, e);
    }
  }

  // 处理已注册的插件
  for (const plugin of window.__registered_plugins) {
    if (plugin.routes) {
      for (const { parent, route } of plugin.routes) {
        if (parent) {
          router.addRoute(parent, route);
          // 同步到菜单树，使侧边栏显示插件菜单
          if (menuRoutes) {
            const parentMenu = menuRoutes.find((m: any) => m.name === parent);
            if (parentMenu?.children) {
              parentMenu.children.push(route);
            }
          }
        } else {
          router.addRoute(route);
          menuRoutes?.push(route);
        }
      }
    }
  }
}

/**
 * 将插件字典合并到目标模块
 * targets 格式：{ funds: fundsDict, order: orderDict, ... }
 * 插件注册格式：{ dictionaries: { funds: { fundPayMethodOptions: [...] } } }
 */
export function mergePluginDictionaries(
  targets: Record<string, Record<string, any>>
) {
  for (const plugin of window.__registered_plugins ?? []) {
    if (!plugin.dictionaries) continue;

    for (const [namespace, entries] of Object.entries(plugin.dictionaries)) {
      const module = targets[namespace];
      if (!module || typeof module !== "object" || Array.isArray(module))
        continue;
      if (typeof entries !== "object" || Array.isArray(entries)) continue;

      for (const [key, value] of Object.entries(entries)) {
        const target = module[key];
        if (!target) continue;

        if (Array.isArray(target) && Array.isArray(value)) {
          target.push(...value);
        } else if (
          typeof target === "object" &&
          !Array.isArray(target) &&
          typeof value === "object" &&
          !Array.isArray(value)
        ) {
          Object.assign(target, value);
        }
      }
    }
  }
}

function validateLocalPath(path: string): void {
  if (!path.startsWith("/") || path.startsWith("//")) {
    throw new Error(`Rejected external URL: ${path}`);
  }
}

function loadScript(src: string): Promise<void> {
  validateLocalPath(src);
  return new Promise((resolve, reject) => {
    const script = document.createElement("script");
    script.src = src;
    const timer = setTimeout(() => {
      script.remove();
      reject(new Error(`Timeout loading: ${src}`));
    }, 30000);
    script.onload = () => {
      clearTimeout(timer);
      resolve();
    };
    script.onerror = () => {
      clearTimeout(timer);
      reject(new Error(`Failed to load: ${src}`));
    };
    document.head.appendChild(script);
  });
}

function loadCSS(href: string): void {
  validateLocalPath(href);
  const link = document.createElement("link");
  link.rel = "stylesheet";
  link.href = href;
  document.head.appendChild(link);
}
