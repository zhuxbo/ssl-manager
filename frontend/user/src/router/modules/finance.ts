export default {
  path: "/finance",
  name: "Finance",
  redirect: "/funds",
  meta: {
    icon: "ri:money-cny-circle-fill",
    title: "财务管理",
    rank: 5
  },
  children: [
    {
      path: "/funds",
      name: "Funds",
      component: () => import("@/views/funds/index.vue"),
      meta: {
        icon: "ri:money-dollar-circle-line",
        title: "资金管理",
        keepAlive: true
      }
    },
    {
      path: "/transaction",
      name: "Transaction",
      component: () => import("@/views/transaction/index.vue"),
      meta: {
        icon: "ri:exchange-line",
        title: "交易记录",
        keepAlive: true
      }
    },
    {
      path: "/invoice",
      name: "Invoice",
      component: () => import("@/views/invoice/index.vue"),
      meta: {
        icon: "ri:file-text-line",
        title: "发票管理",
        keepAlive: true
      }
    },
    {
      path: "/invoice-limit",
      name: "InvoiceLimit",
      component: () => import("@/views/invoiceLimit/index.vue"),
      meta: {
        icon: "ri:money-dollar-box-line",
        title: "发票额度",
        keepAlive: true
      }
    },
    {
      path: "/tid/:tid",
      name: "PlatformRecharge",
      redirect: ((to: { params: { tid: string } }) => {
        return `/funds?tid=${to.params.tid}`;
      }) as any,
      meta: {
        title: "平台充值",
        showLink: false
      }
    }
  ]
} satisfies RouteConfigsTable;
