export const fundPayMethodOptions: { label: string; value: string }[] = [
  {
    label: "支付宝",
    value: "alipay"
  },
  {
    label: "微信",
    value: "wechat"
  },
  {
    label: "银行卡",
    value: "credit"
  },
  {
    label: "赠送",
    value: "gift"
  },
  {
    label: "其他",
    value: "other"
  }
];

export const fundTypeOptions = [
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
  }
];

export const storeFundStatusOptions = [
  {
    label: "处理中",
    value: 0
  },
  {
    label: "成功",
    value: 1
  }
];

export const fundStatusOptions = [
  {
    label: "处理中",
    value: 0
  },
  {
    label: "成功",
    value: 1
  },
  {
    label: "已退",
    value: 2
  }
];

export const fundPayMethodMap: Record<string, string> = {
  alipay: "success",
  wechat: "success",
  credit: "success",
  gift: "info",
  other: "warning"
};

export const fundTypeMap = {
  addfunds: "success",
  refunds: "danger",
  deduct: "primary",
  reverse: "danger"
};

export const fundStatusMap = {
  0: "primary",
  1: "success",
  2: "danger"
};
