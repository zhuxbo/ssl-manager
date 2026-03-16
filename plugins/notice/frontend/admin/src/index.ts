import NoticeIndex from "./views/notice/index.vue";

window.__registerPlugin({
  name: "notice",
  routes: [
    {
      parent: "System",
      route: {
        path: "/notice",
        name: "Notice",
        component: NoticeIndex,
        meta: {
          icon: "ep:bell",
          title: "公告管理",
          keepAlive: true
        }
      }
    }
  ]
});
