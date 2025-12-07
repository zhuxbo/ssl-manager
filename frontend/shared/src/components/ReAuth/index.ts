import ReAuth, { setHasAuthForAuth } from "./src/auth";
import type { HasAuthFn } from "./src/auth";

// 导出组件，同时保留 Auth 别名以保持向后兼容
const Auth = ReAuth;

export { ReAuth, Auth, setHasAuthForAuth };
export type { HasAuthFn };
export default ReAuth;
