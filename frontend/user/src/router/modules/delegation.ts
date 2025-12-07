export default {
  path: "/delegations",
  name: "Delegations",
  redirect: "/delegation",
  meta: {
    icon: "ri:flip-vertical-fill",
    title: "域名委托",
    rank: 3.1
  },
  children: [
    {
      path: "/delegation",
      name: "Delegation",
      component: () => import("@/views/delegation/index.vue"),
      meta: {
        title: "域名委托",
        keepAlive: true
      }
    }
  ]
} satisfies RouteConfigsTable;
