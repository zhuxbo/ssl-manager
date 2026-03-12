export default {
  path: "/acme-orders",
  name: "AcmeOrders",
  redirect: "/acme-order",
  meta: {
    icon: "ri:robot-2-fill",
    title: "ACME订单",
    rank: 3.5,
    showLink: false
  },
  children: [
    {
      path: "/acme-order",
      name: "AcmeOrder",
      component: () => import("@/views/acme-order/index.vue"),
      meta: {
        title: "ACME订单",
        keepAlive: true
      }
    },
    {
      path: "/acme-order/details/:ids",
      name: "AcmeOrderDetails",
      component: () => import("@/views/acme-order/details.vue"),
      meta: {
        title: "ACME订单详情",
        activePath: "/acme-order",
        showLink: false
      }
    }
  ]
} satisfies RouteConfigsTable;
