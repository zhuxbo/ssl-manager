import pureTableBar from "./src/bar";
import { withInstall } from "@pureadmin/utils";

// 使用 withInstall 包装，与原组件保持一致
export const PureTableBar = withInstall(pureTableBar);
export const RePureTableBar = PureTableBar;

export { setEpThemeColorGetter } from "./src/bar";
export default PureTableBar;
