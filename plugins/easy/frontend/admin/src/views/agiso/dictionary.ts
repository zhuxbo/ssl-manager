export const payMethodOptions = [
  { label: "其他", value: "other" },
  { label: "支付宝", value: "alipay" },
  { label: "微信", value: "wechat" },
  { label: "银行卡", value: "credit" },
  { label: "赠送", value: "gift" },
  { label: "淘宝", value: "taobao" },
  { label: "拼多多", value: "pinduoduo" },
  { label: "京东", value: "jingdong" },
  { label: "抖音", value: "douyin" }
];

export const payMethodMap: Record<string, string> = {
  other: "info",
  alipay: "success",
  wechat: "success",
  credit: "success",
  gift: "success",
  taobao: "primary",
  pinduoduo: "warning",
  jingdong: "info",
  douyin: "info"
};

export const rechargedOptions = [
  { label: "未充值", value: 0 },
  { label: "已充值", value: 1 }
];

export const rechargedMap: Record<number, string> = {
  0: "info",
  1: "success"
};
