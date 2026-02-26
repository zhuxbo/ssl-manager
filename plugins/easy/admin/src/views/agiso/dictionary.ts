export const platformOptions = [
  { label: "淘宝", value: "TbAlds" },
  { label: "拼多多", value: "PddAlds" },
  { label: "京东", value: "AldsJd" },
  { label: "抖音", value: "AldsDoudian" }
];

export const platformMap: Record<string, string> = {
  TbAlds: "primary",
  PddAlds: "warning",
  AldsJd: "info",
  AldsDoudian: "info"
};

export const rechargedOptions = [
  { label: "未充值", value: 0 },
  { label: "已充值", value: 1 }
];

export const rechargedMap: Record<number, string> = {
  0: "info",
  1: "success"
};
