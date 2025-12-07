export const invoiceLimitTypeOptions = [
  {
    label: "开票",
    value: "issue"
  },
  {
    label: "作废",
    value: "void"
  },
  {
    label: "充值",
    value: "addfunds"
  },
  {
    label: "退款",
    value: "refunds"
  }
];

export const invoiceLimitTypeMap = {
  issue: "success",
  void: "danger",
  addfunds: "primary",
  refunds: "warning"
};
