export const fundPayMethodOptions = [
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
    label: "淘宝",
    value: "taobao"
  },
  {
    label: "拼多多",
    value: "pinduoduo"
  },
  {
    label: "京东",
    value: "jingdong"
  },
  {
    label: "抖音",
    value: "douyin"
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

export const fundStatusOptions = [
  {
    label: "成功",
    value: 1
  },
  {
    label: "已退",
    value: 2
  }
];

export const fundPayMethodMap = {
  alipay: "success",
  wechat: "success",
  credit: "success",
  taobao: "primary",
  pinduoduo: "primary",
  jingdong: "primary",
  douyin: "primary",
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
  1: "success",
  2: "danger"
};
