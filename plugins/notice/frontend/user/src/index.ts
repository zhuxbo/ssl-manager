import NoticeBanner from "./views/NoticeBanner.vue";
import NoticePopup from "./views/NoticePopup.vue";
import { h } from "vue";

const DashboardBanner = () => h(NoticeBanner, { position: "dashboard" });
const OrderBanner = () => h(NoticeBanner, { position: "order" });
const ProductBanner = () => h(NoticeBanner, { position: "product" });

window.__registerPlugin({
  name: "notice",
  widgets: [
    { slot: "user-dashboard-top", component: DashboardBanner },
    { slot: "user-order-top", component: OrderBanner },
    { slot: "user-product-top", component: ProductBanner },
    { slot: "user-global", component: NoticePopup }
  ]
});
