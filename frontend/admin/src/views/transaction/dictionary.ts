export const transactionTypeOptions = [
  {
    label: "充值",
    value: "addfunds"
  },
  {
    label: "退款",
    value: "refunds"
  },
  {
    label: "扣款",
    value: "deduct"
  },
  {
    label: "退回",
    value: "reverse"
  },
  {
    label: "订单",
    value: "order"
  },
  {
    label: "取消",
    value: "cancel"
  },
  {
    label: "ACME订阅",
    value: "acme_order"
  },
  {
    label: "ACME取消",
    value: "acme_cancel"
  }
];

export const transactionTypeMap = {
  addfunds: "success",
  refunds: "danger",
  deduct: "primary",
  reverse: "danger",
  order: "primary",
  cancel: "danger",
  acme_order: "primary",
  acme_cancel: "danger"
};
