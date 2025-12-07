export default {
  path: "/orders",
  name: "Orders",
  redirect: "/order",
  meta: {
    icon: "ri:shopping-cart-fill",
    title: "订单管理",
    rank: 3
  },
  children: [
    {
      path: "/order",
      name: "Order",
      component: () => import("@/views/order/index.vue"),
      meta: {
        title: "订单管理",
        keepAlive: true
      }
    },
    {
      path: "/order/details/:ids",
      name: "OrderDetails",
      component: () => import("@/views/order/details.vue"),
      meta: {
        title: "订单详情",
        activePath: "/order",
        showLink: false
      }
    }
  ]
} satisfies RouteConfigsTable;
