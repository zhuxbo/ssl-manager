import NoticeBanner from "./views/NoticeBanner.vue";

window.__registerPlugin({
  name: "notice",
  widgets: [{ slot: "user-dashboard-top", component: NoticeBanner }]
});
