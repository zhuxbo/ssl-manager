export default {
  path: "/products",
  name: "Products",
  redirect: "/product",
  meta: {
    icon: "ep:goods-filled",
    title: "购买证书",
    rank: 2
  },
  children: [
    {
      path: "/product",
      name: "Product",
      component: () => import("@/views/product/index.vue"),
      meta: {
        title: "购买证书",
        keepAlive: true
      }
    }
  ]
} satisfies RouteConfigsTable;
