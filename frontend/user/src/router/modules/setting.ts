export default {
  path: "/users",
  name: "Users",
  redirect: "/setting",
  meta: {
    icon: "ri:user-fill",
    title: "会员中心",
    rank: 9
  },
  children: [
    {
      path: "/setting",
      name: "Setting",
      component: () => import("@/views/setting/index.vue"),
      meta: {
        icon: "ri:settings-3-line",
        title: "设置",
        keepAlive: true
      }
    },
    {
      path: "/organization",
      name: "Organization",
      component: () => import("@/views/organization/index.vue"),
      meta: {
        icon: "ri:building-line",
        title: "组织",
        keepAlive: true
      }
    },
    {
      path: "/contact",
      name: "Contact",
      component: () => import("@/views/contact/index.vue"),
      meta: {
        icon: "ri:contacts-line",
        title: "联系人",
        keepAlive: true
      }
    }
  ]
} satisfies RouteConfigsTable;
