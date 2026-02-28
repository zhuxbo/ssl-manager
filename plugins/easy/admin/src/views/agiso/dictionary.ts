export const platformOptions = [
  { label: "淘宝", value: "TbAlds" },
  { label: "拼多多", value: "PddAlds" },
  { label: "京东", value: "AldsJd" },
  { label: "抖音", value: "AldsDoudian" },
  { label: "赠送", value: "gift" }
];

export const platformMap: Record<string, string> = {
  TbAlds: "primary",
  PddAlds: "warning",
  AldsJd: "info",
  AldsDoudian: "info",
  gift: "success"
};

export const payMethodOptions = [
  { label: "其他", value: "other" },
  { label: "赠送", value: "gift" },
  { label: "淘宝", value: "taobao" },
  { label: "拼多多", value: "pinduoduo" },
  { label: "京东", value: "jingdong" },
  { label: "抖音", value: "douyin" }
];

export const rechargedOptions = [
  { label: "未充值", value: 0 },
  { label: "已充值", value: 1 }
];

export const rechargedMap: Record<number, string> = {
  0: "info",
  1: "success"
};
