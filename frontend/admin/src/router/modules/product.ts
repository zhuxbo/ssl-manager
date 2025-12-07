export default {
  path: "/products",
  name: "Products",
  redirect: "/product",
  meta: {
    icon: "ep:goods-filled",
    title: "产品管理",
    rank: 4
  },
  children: [
    {
      path: "/product",
      name: "Product",
      component: () => import("@/views/product/index.vue"),
      meta: {
        icon: "ri:product-hunt-line",
        title: "产品管理",
        keepAlive: true
      }
    },
    {
      path: "/product-price",
      name: "ProductPrice",
      component: () => import("@/views/productPrice/index.vue"),
      meta: {
        icon: "ri:price-tag-3-line",
        title: "产品价格"
      }
    }
  ]
} satisfies RouteConfigsTable;
