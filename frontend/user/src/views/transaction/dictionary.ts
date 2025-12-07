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
  }
];

export const transactionTypeMap = {
  addfunds: "success",
  refunds: "danger",
  deduct: "primary",
  reverse: "danger",
  order: "primary",
  cancel: "danger"
};
