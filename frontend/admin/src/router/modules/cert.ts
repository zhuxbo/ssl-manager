export default {
  path: "/certs",
  name: "Certs",
  redirect: "/cert",
  meta: {
    icon: "ri:shield-check-fill",
    title: "证书管理",
    rank: 2
  },
  children: [
    {
      path: "/cert",
      name: "Cert",
      component: () => import("@/views/cert/index.vue"),
      meta: {
        icon: "ri:shield-check-line",
        title: "证书管理",
        keepAlive: true
      }
    },
    {
      path: "/chain",
      name: "Chain",
      component: () => import("@/views/chain/index.vue"),
      meta: {
        icon: "ri:links-line",
        title: "证书链管理",
        keepAlive: true
      }
    }
  ]
} satisfies RouteConfigsTable;
