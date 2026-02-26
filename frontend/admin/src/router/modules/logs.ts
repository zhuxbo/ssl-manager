export default {
  path: "/logs",
  name: "Logs",
  redirect: "/logs/admin",
  meta: {
    icon: "ri:file-list-fill",
    title: "日志",
    rank: 98
  },
  children: [
    {
      path: "/logs/admin",
      name: "AdminLogs",
      component: () => import("@/views/logs/admin/index.vue"),
      meta: {
        icon: "ri:admin-line",
        title: "管理员日志",
        keepAlive: true
      }
    },
    {
      path: "/logs/user",
      name: "UserLogs",
      component: () => import("@/views/logs/user/index.vue"),
      meta: {
        icon: "ri:user-line",
        title: "用户日志",
        keepAlive: true
      }
    },
    {
      path: "/logs/api",
      name: "ApiLogs",
      component: () => import("@/views/logs/api/index.vue"),
      meta: {
        icon: "ri:key-line",
        title: "API日志",
        keepAlive: true
      }
    },
    {
      path: "/logs/ca",
      name: "CaLogs",
      component: () => import("@/views/logs/ca/index.vue"),
      meta: {
        icon: "ri:send-plane-line",
        title: "CA日志",
        keepAlive: true
      }
    },
    {
      path: "/logs/callback",
      name: "CallbackLogs",
      component: () => import("@/views/logs/callback/index.vue"),
      meta: {
        icon: "ri:webhook-line",
        title: "回调日志",
        keepAlive: true
      }
    },
    {
      path: "/logs/error",
      name: "ErrorLogs",
      component: () => import("@/views/logs/error/index.vue"),
      meta: {
        icon: "ri:error-warning-line",
        title: "错误日志",
        keepAlive: true
      }
    }
  ]
} satisfies RouteConfigsTable;
