import AgisoIndex from "./views/agiso/index.vue";
import EasyLogsIndex from "./views/logs/easy/index.vue";

window.__registerPlugin({
  name: "easy",
  routes: [
    {
      parent: "Finance",
      route: {
        path: "/agiso",
        name: "Agiso",
        component: AgisoIndex,
        meta: {
          icon: "ri:store-line",
          title: "电商平台",
          keepAlive: true
        }
      }
    },
    {
      parent: "Logs",
      route: {
        path: "/logs/easy",
        name: "EasyLogs",
        component: EasyLogsIndex,
        meta: {
          icon: "ri:file-edit-line",
          title: "简易申请日志",
          keepAlive: true
        }
      }
    }
  ],
  dictionaries: {
    funds: {
      fundPayMethodOptions: [
        { label: "淘宝", value: "taobao" },
        { label: "拼多多", value: "pinduoduo" },
        { label: "京东", value: "jingdong" },
        { label: "抖音", value: "douyin" }
      ],
      fundPayMethodMap: {
        taobao: "primary",
        pinduoduo: "warning",
        jingdong: "info",
        douyin: "info"
      }
    }
  }
});
