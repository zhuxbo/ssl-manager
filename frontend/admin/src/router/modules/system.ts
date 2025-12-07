export default {
  path: "/system",
  name: "System",
  redirect: "/task",
  meta: {
    icon: "ri:settings-5-fill",
    title: "系统",
    rank: 99
  },
  children: [
    {
      path: "/task",
      name: "Task",
      component: () => import("@/views/task/index.vue"),
      meta: {
        icon: "ri:task-line",
        title: "任务管理",
        keepAlive: true
      }
    },
    {
      path: "/notification/records",
      name: "NotificationRecords",
      component: () => import("@/views/notification/record/index.vue"),
      meta: {
        icon: "ri:history-line",
        title: "通知记录",
        keepAlive: true
      }
    },
    {
      path: "/notification/templates",
      name: "NotificationTemplate",
      component: () => import("@/views/notification/template/index.vue"),
      meta: {
        icon: "ri:article-line",
        title: "通知模板",
        keepAlive: true
      }
    },
    {
      path: "/setting",
      name: "Setting",
      component: () => import("@/views/setting/index.vue"),
      meta: {
        icon: "ri:settings-3-line",
        title: "系统设置",
        keepAlive: true
      }
    }
  ]
} satisfies RouteConfigsTable;
