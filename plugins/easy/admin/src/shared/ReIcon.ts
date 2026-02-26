import { h, defineComponent, resolveComponent, type Component } from "vue";

/**
 * 简化版 useRenderIcon
 * 插件环境下使用主系统已注册的全局图标组件
 */
export function useRenderIcon(icon: any, attrs?: any): Component {
  if (typeof icon === "function" || typeof icon?.render === "function") {
    return attrs ? h(icon, { ...attrs }) : icon;
  }

  if (typeof icon === "object") {
    return defineComponent({
      name: "PluginIcon",
      render() {
        const IconComp = resolveComponent("IconifyIconOffline");
        return h(IconComp, { icon, ...attrs });
      }
    });
  }

  return defineComponent({
    name: "PluginIcon",
    render() {
      if (!icon) return;
      const compName =
        typeof icon === "string" && icon.includes(":")
          ? "IconifyIconOnline"
          : "IconifyIconOffline";
      const IconComp = resolveComponent(compName);
      return h(IconComp, { icon, ...attrs });
    }
  });
}
