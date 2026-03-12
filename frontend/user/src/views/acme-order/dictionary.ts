import type { EpPropMergeType } from "element-plus/es/utils/vue/props/types";

export const statusType: {
  [key: string]: EpPropMergeType<
    StringConstructor,
    "info" | "primary" | "success" | "warning" | "danger",
    unknown
  >;
} = {
  processing: "primary",
  approving: "primary",
  active: "success",
  failed: "danger",
  cancelling: "danger",
  revoking: "danger",
  cancelled: "danger",
  renewed: "info",
  reissued: "info",
  expired: "info",
  revoked: "danger"
};

export const status: { [key: string]: string } = {
  processing: "待验证",
  approving: "待审核",
  active: "已签发",
  cancelling: "待取消",
  revoking: "待吊销",
  cancelled: "已取消",
  renewed: "已续期",
  reissued: "已重签",
  expired: "已过期",
  revoked: "已吊销",
  failed: "已失败"
};

export const statusOptions: { label: string; value: string }[] = [
  { label: "待验证", value: "processing" },
  { label: "待审核", value: "approving" },
  { label: "已签发", value: "active" },
  { label: "待取消", value: "cancelling" },
  { label: "待吊销", value: "revoking" },
  { label: "已取消", value: "cancelled" },
  { label: "已续期", value: "renewed" },
  { label: "已重签", value: "reissued" },
  { label: "已过期", value: "expired" },
  { label: "已吊销", value: "revoked" },
  { label: "已失败", value: "failed" }
];

export const action: { [key: string]: string } = {
  new: "新购",
  renew: "续期",
  reissue: "重签"
};

export const actionType: {
  [key: string]: EpPropMergeType<
    StringConstructor,
    "info" | "primary" | "success" | "warning" | "danger",
    unknown
  >;
} = {
  new: "success",
  renew: "success",
  reissue: "info"
};

export const validationMethod: { [key: string]: string } = {
  delegation: "委托验证",
  txt: "TXT 验证",
  file_proxy: "文件代理",
  file: "文件验证"
};
