import type { Directive, DirectiveBinding } from "vue";

/** hasAuth 函数类型 */
export type HasAuthFn = (value: string | Array<string>) => boolean;

/** 全局 hasAuth 函数 */
let hasAuthFn: HasAuthFn | null = null;

/**
 * 设置 hasAuth 函数（应用启动时调用）
 * @param fn hasAuth 函数实现
 */
export function setHasAuth(fn: HasAuthFn): void {
  hasAuthFn = fn;
}

/**
 * 获取 hasAuth 函数
 */
export function getHasAuth(): HasAuthFn | null {
  return hasAuthFn;
}

export const auth: Directive = {
  mounted(el: HTMLElement, binding: DirectiveBinding<string | Array<string>>) {
    const { value } = binding;
    if (value) {
      if (!hasAuthFn) {
        console.warn("[Directive: auth]: hasAuth function not initialized. Call setHasAuth() first.");
        return;
      }
      !hasAuthFn(value) && el.parentNode?.removeChild(el);
    } else {
      throw new Error(
        "[Directive: auth]: need auths! Like v-auth=\"['btn.add','btn.edit']\""
      );
    }
  }
};
