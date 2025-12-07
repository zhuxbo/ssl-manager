// 这里存放本地图标，在 src/layout/index.vue 文件中加载，避免在首启动加载
import { getSvgInfo } from "@pureadmin/utils";
import { addIcon } from "@iconify/vue/dist/offline";

// https://icon-sets.iconify.design/ep/?keyword=ep
import EpHomeFilled from "~icons/ep/home-filled?raw";
import EpMenu from "~icons/ep/menu?raw";
import EpEdit from "~icons/ep/edit?raw";
import EpSetUp from "~icons/ep/set-up?raw";
import EpGuide from "~icons/ep/guide?raw";
import EpMonitor from "~icons/ep/monitor?raw";
import EpLollipop from "~icons/ep/lollipop?raw";
import EpHistogram from "~icons/ep/histogram?raw";
import EpCopyDocument from "~icons/ep/copy-document?raw";
import EpCirclePlus from "~icons/ep/circle-plus?raw";

// https://icon-sets.iconify.design/ri/?keyword=ri
import RiSearchLine from "~icons/ri/search-line?raw";
import RiInformationLine from "~icons/ri/information-line?raw";
import RiBookmark2Line from "~icons/ri/bookmark-2-line?raw";
import RiFilePpt2Line from "~icons/ri/file-ppt-2-line?raw";
import RiBankCardLine from "~icons/ri/bank-card-line?raw";
import RiAdminFill from "~icons/ri/admin-fill?raw";
import RiFileInfoLine from "~icons/ri/file-info-line?raw";
import RiGitBranchLine from "~icons/ri/git-branch-line?raw";
import RiTableLine from "~icons/ri/table-line?raw";
import RiLinksFill from "~icons/ri/links-fill?raw";
import RiAdminLine from "~icons/ri/admin-line?raw";
import RiSettings3Line from "~icons/ri/settings-3-line?raw";
import RiMindMap from "~icons/ri/mind-map?raw";
import RiBarChartHorizontalLine from "~icons/ri/bar-chart-horizontal-line?raw";
import RiWindowLine from "~icons/ri/window-line?raw";
import RiArtboardLine from "~icons/ri/artboard-line?raw";
import RiFileSearchLine from "~icons/ri/file-search-line?raw";
import RiListCheck from "~icons/ri/list-check?raw";
import RiUbuntuFill from "~icons/ri/ubuntu-fill?raw";
import RiUserVoiceLine from "~icons/ri/user-voice-line?raw";
import RiEditBoxLine from "~icons/ri/edit-box-line?raw";
import RiHistoryFill from "~icons/ri/history-fill?raw";
import RiTerminalWindowLine from "~icons/ri/terminal-window-line?raw";
import RiCheckboxCircleLine from "~icons/ri/checkbox-circle-line?raw";
import RiDownloadLine from "~icons/ri/download-line?raw";
import RiDownloadFill from "~icons/ri/download-fill?raw";
import RiDownload2Line from "~icons/ri/download-2-line?raw";
import RiDownload2Fill from "~icons/ri/download-2-fill?raw";

const icons = [
  // Element Plus Icon: https://github.com/element-plus/element-plus-icons
  ["ep/home-filled", EpHomeFilled],
  ["ep/menu", EpMenu],
  ["ep/edit", EpEdit],
  ["ep/set-up", EpSetUp],
  ["ep/guide", EpGuide],
  ["ep/monitor", EpMonitor],
  ["ep/lollipop", EpLollipop],
  ["ep/histogram", EpHistogram],
  ["ep/copy-document", EpCopyDocument],
  ["ep/circle-plus", EpCirclePlus],
  // Remix Icon: https://github.com/Remix-Design/RemixIcon
  ["ri/search-line", RiSearchLine],
  ["ri/information-line", RiInformationLine],
  ["ri/bookmark-2-line", RiBookmark2Line],
  ["ri/file-ppt-2-line", RiFilePpt2Line],
  ["ri/bank-card-line", RiBankCardLine],
  ["ri/admin-fill", RiAdminFill],
  ["ri/file-info-line", RiFileInfoLine],
  ["ri/git-branch-line", RiGitBranchLine],
  ["ri/table-line", RiTableLine],
  ["ri/links-fill", RiLinksFill],
  ["ri/admin-line", RiAdminLine],
  ["ri/settings-3-line", RiSettings3Line],
  ["ri/mind-map", RiMindMap],
  ["ri/bar-chart-horizontal-line", RiBarChartHorizontalLine],
  ["ri/window-line", RiWindowLine],
  ["ri/artboard-line", RiArtboardLine],
  ["ri/file-search-line", RiFileSearchLine],
  ["ri/list-check", RiListCheck],
  ["ri/ubuntu-fill", RiUbuntuFill],
  ["ri/user-voice-line", RiUserVoiceLine],
  ["ri/edit-box-line", RiEditBoxLine],
  ["ri/history-fill", RiHistoryFill],
  ["ri/terminal-window-line", RiTerminalWindowLine],
  ["ri/checkbox-circle-line", RiCheckboxCircleLine],
  ["ri/download-line", RiDownloadLine],
  ["ri/download-fill", RiDownloadFill],
  ["ri/download-2-line", RiDownload2Line],
  ["ri/download-2-fill", RiDownload2Fill]
];

// 本地菜单图标，后端在路由的 icon 中返回对应的图标字符串并且前端在此处使用 addIcon 添加即可渲染菜单图标
icons.forEach(([name, icon]) => {
  addIcon(name as string, getSvgInfo(icon as string));
});
