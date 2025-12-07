// 响应式storage
import type { App } from "vue";
import Storage from "responsive-storage";
import { responsiveStorageNameSpace } from "../config";

export interface InjectResponsiveStorageOptions {
  /** 默认的路由标签数组 */
  routerArrays?: Array<any>;
}

export const injectResponsiveStorage = (
  app: App,
  config: PlatformConfigs,
  options: InjectResponsiveStorageOptions = {}
) => {
  const { routerArrays = [] } = options;
  const nameSpace = responsiveStorageNameSpace();
  const configObj = Object.assign(
    {
      // layout模式以及主题
      layout: Storage.getData("layout", nameSpace) ?? {
        layout: config.Layout ?? "vertical",
        theme: config.Theme ?? "light",
        darkMode: config.DarkMode ?? false,
        sidebarStatus: config.SidebarStatus ?? true,
        epThemeColor: config.EpThemeColor ?? "#409EFF",
        themeColor: config.Theme ?? "light", // 主题色（对应系统配置中的主题色，与theme不同的是它不会受到浅色、深色整体风格切换的影响，只会在手动点击主题色时改变）
        overallStyle: config.OverallStyle ?? "light" // 整体风格（浅色：light、深色：dark、自动：system）
      },
      // 系统配置-界面显示
      configure: Storage.getData("configure", nameSpace) ?? {
        grey: config.Grey ?? false,
        weak: config.Weak ?? false,
        hideTabs: config.HideTabs ?? false,
        hideFooter: config.HideFooter ?? true,
        showLogo: config.ShowLogo ?? true,
        showModel: config.ShowModel ?? "smart",
        multiTagsCache: config.MultiTagsCache ?? false,
        stretch: config.Stretch ?? false
      },
      // 后端配置-基础路径
      backend: Storage.getData("backend", nameSpace) ?? {
        baseUrlApi: config.BaseUrlApi ?? "/api"
      }
    },
    config.MultiTagsCache
      ? {
          // 默认显示顶级菜单tag
          tags: Storage.getData("tags", nameSpace) ?? routerArrays
        }
      : {}
  );

  app.use(Storage, { nameSpace, memory: configObj });
};
