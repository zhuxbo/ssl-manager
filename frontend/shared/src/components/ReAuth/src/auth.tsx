import { defineComponent, Fragment } from "vue";

// 类型定义
export type HasAuthFn = (value: string | string[]) => boolean;

// 依赖注入：hasAuth 函数
let hasAuthFn: HasAuthFn | null = null;

/**
 * 设置 hasAuth 函数（检查路由 meta.auths 的按钮级权限）
 * 需要在应用启动时调用
 */
export function setHasAuthForAuth(fn: HasAuthFn): void {
  hasAuthFn = fn;
}

export default defineComponent({
  name: "Auth",
  props: {
    value: {
      type: undefined,
      default: []
    }
  },
  setup(props, { slots }) {
    return () => {
      if (!slots) return null;
      if (!hasAuthFn) {
        console.warn("[ReAuth] hasAuth function not initialized. Call setHasAuthForAuth() first.");
        return null;
      }
      return hasAuthFn(props.value) ? (
        <Fragment>{slots.default?.()}</Fragment>
      ) : null;
    };
  }
});
