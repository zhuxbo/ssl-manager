export default {
  path: "/acmes",
  name: "Acmes",
  redirect: "/acme",
  meta: {
    icon: "ri:robot-2-fill",
    title: "ACME订阅",
    rank: 3.5,
    showLink: false
  },
  children: [
    {
      path: "/acme",
      name: "Acme",
      component: () => import("@/views/acme/index.vue"),
      meta: {
        title: "ACME订阅",
        keepAlive: true
      }
    },
    {
      path: "/acme/details/:ids",
      name: "AcmeDetails",
      component: () => import("@/views/acme/details.vue"),
      meta: {
        title: "ACME订阅详情",
        activePath: "/acme",
        showLink: false
      }
    }
  ]
} satisfies RouteConfigsTable;
