const Layout = () => import("@/layout/index.vue");

export default [
  {
    path: "/login",
    name: "Login",
    component: () => import("@/views/login/index.vue"),
    meta: {
      title: "登录",
      showLink: false,
      rank: 101
    }
  },
  {
    path: "/register",
    name: "Register",
    component: () => import("@/views/register/index.vue"),
    meta: {
      title: "注册",
      showLink: false,
      rank: 102
    }
  },
  {
    path: "/reset-password",
    name: "ResetPassword",
    component: () => import("@/views/resetPassword/index.vue"),
    meta: {
      title: "找回密码",
      showLink: false,
      rank: 103
    }
  },
  {
    path: "/tb",
    name: "TaoBao",
    redirect: (() => {
      return `/register?source=taobao`;
    }) as any,
    meta: {
      title: "淘宝",
      showLink: false
    }
  },
  {
    path: "/pdd",
    name: "Pinduoduo",
    redirect: (() => {
      return `/register?source=pinduoduo`;
    }) as any,
    meta: {
      title: "拼多多",
      showLink: false
    }
  },
  {
    path: "/redirect",
    component: Layout,
    meta: {
      title: "加载中...",
      showLink: false,
      rank: 104
    },
    children: [
      {
        path: "/redirect/:path(.*)",
        name: "Redirect",
        component: () => import("@/layout/redirect.vue")
      }
    ]
  }
] satisfies Array<RouteConfigsTable>;
