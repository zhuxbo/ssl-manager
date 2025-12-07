export default {
  path: "/admins",
  name: "Admins",
  redirect: "/me",
  meta: {
    icon: "ri:admin-fill",
    title: "管理员",
    rank: 6
  },
  children: [
    {
      path: "/me",
      name: "Me",
      component: () => import("@/views/admin/me.vue"),
      meta: {
        icon: "ri:user-settings-fill",
        title: "个人资料",
        keepAlive: true
      }
    },
    {
      path: "/admin",
      name: "Admin",
      component: () => import("@/views/admin/index.vue"),
      meta: {
        icon: "ri:admin-line",
        title: "管理员管理",
        keepAlive: true
      }
    }
  ]
} satisfies RouteConfigsTable;
