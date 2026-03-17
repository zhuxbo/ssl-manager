import ReAuth, { setHasAuthForAuth } from "./src/auth";

// 导出组件，同时保留 Auth 别名以保持向后兼容
const Auth = ReAuth;

// HasAuthFn 不从此处导出，避免与 directives/auth 的同名导出冲突
// 使用方应从 @shared/directives 导入 HasAuthFn
export { ReAuth, Auth, setHasAuthForAuth };
export default ReAuth;
