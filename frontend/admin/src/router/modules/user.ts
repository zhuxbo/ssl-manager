export default {
  path: "/users",
  name: "Users",
  redirect: "/user",
  meta: {
    icon: "ri:user-fill",
    title: "用户管理",
    rank: 5
  },
  children: [
    {
      path: "/user",
      name: "User",
      component: () => import("@/views/user/index.vue"),
      meta: {
        icon: "ri:user-line",
        title: "用户管理",
        keepAlive: true
      }
    },
    {
      path: "/user-level",
      name: "UserLevel",
      component: () => import("@/views/userLevel/index.vue"),
      meta: {
        icon: "ri:vip-crown-line",
        title: "用户级别",
        keepAlive: true
      }
    },
    {
      path: "/organization",
      name: "Organization",
      component: () => import("@/views/organization/index.vue"),
      meta: {
        icon: "ri:building-line",
        title: "组织管理",
        keepAlive: true
      }
    },
    {
      path: "/contact",
      name: "Contact",
      component: () => import("@/views/contact/index.vue"),
      meta: {
        icon: "ri:contacts-line",
        title: "联系人管理",
        keepAlive: true
      }
    },
    {
      path: "/api-token",
      name: "ApiToken",
      component: () => import("@/views/apiToken/index.vue"),
      meta: {
        icon: "ri:key-line",
        title: "接口令牌",
        keepAlive: true
      }
    },
    {
      path: "/callback",
      name: "Callback",
      component: () => import("@/views/callback/index.vue"),
      meta: {
        icon: "ri:webhook-line",
        title: "回调管理",
        keepAlive: true
      }
    }
  ]
} satisfies RouteConfigsTable;
