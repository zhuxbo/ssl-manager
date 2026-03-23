import InvoiceIndex from "./views/invoice/index.vue";

window.__registerPlugin({
  name: "invoice",
  routes: [
    {
      parent: "Finance",
      route: {
        path: "/invoice",
        name: "Invoice",
        component: InvoiceIndex,
        meta: {
          icon: "ri:file-text-line",
          title: "发票管理",
          keepAlive: true
        }
      }
    }
  ]
});
