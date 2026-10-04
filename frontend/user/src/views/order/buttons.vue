<template>
  <div class="flex items-center gap-2">
    <el-button
      v-if="['unpaid'].includes(row.latest_cert?.status)"
      class="reset-margin !outline-none"
      type="primary"
      link
      :size="size"
      @click="handlePay(row)"
    >
      支付
    </el-button>
    <el-button
      v-if="['active'].includes(row.latest_cert?.status)"
      class="reset-margin !outline-none"
      type="primary"
      link
      :size="size"
      @click="handleDownload(row)"
    >
      下载
    </el-button>
    <el-button
      v-if="['pending'].includes(row.latest_cert?.status)"
      class="reset-margin !outline-none"
      type="success"
      link
      :size="size"
      @click="handleCommit(row)"
    >
      提交
    </el-button>
    <el-button
      class="reset-margin !outline-none"
      type="primary"
      link
      :size="size"
      @click="handleView(row)"
    >
      查看
    </el-button>
    <el-button
      v-if="['processing'].includes(row.latest_cert?.status) && hasDcvInfo(row)"
      class="reset-margin !outline-none"
      type="primary"
      link
      :size="size"
      @click="handleRevalidate(row)"
    >
      验证
    </el-button>
    <el-button
      v-if="
        ['processing', 'active', 'approving'].includes(row.latest_cert?.status)
      "
      class="reset-margin !outline-none"
      type="primary"
      link
      :size="size"
      @click="handleSync(row)"
    >
      同步
    </el-button>
    <el-button
      v-if="allowCancel(row)"
      class="reset-margin !outline-none"
      type="danger"
      link
      :size="size"
      @click="handleCancel(row)"
      >取消</el-button
    >
    <el-button
      v-if="canRevokeOrderCancellation(row, now.getTime())"
      class="reset-margin !outline-none"
      type="warning"
      link
      :size="size"
      @click="handleRevokeCancel(row)"
      >撤回</el-button
    >
  </div>
</template>

<script setup lang="ts">
import { useNow } from "@vueuse/core";
import { canRevokeOrderCancellation } from "@shared/utils/orderCancellation";
import * as OrderApi from "@/api/order";
import { message } from "@shared/utils";
import { useDetail } from "./detail";
import dayjs from "dayjs";
import {
  confirmOrderCancellation,
  cancellationMessage
} from "@shared/utils/orderConfirmation";

const now = useNow({ interval: 1000 });

const { toDetail } = useDetail();

const emit = defineEmits<{
  (e: "refresh"): void;
}>();

defineProps<{
  row: any;
  size?: "default" | "small" | "large";
}>();

const hasDcvInfo = (row: any) => {
  const dcv = row.latest_cert?.dcv;
  if (!dcv) return false;

  // 委托验证检查 validation 数组
  if (dcv.is_delegate) {
    const validation = row.latest_cert?.validation;
    return validation && validation.length > 0;
  }

  // 普通验证检查 dns 或 file 信息
  return dcv.dns?.value || dcv.file?.name;
};

const allowCancel = (row: any) => {
  if (["unpaid", "pending"].includes(row.latest_cert?.status)) {
    return true;
  }
  return (
    ["processing", "approving", "active"].includes(row.latest_cert?.status) &&
    dayjs().diff(dayjs(row.created_at), "seconds") <
      (row.product?.refund_period ?? 0) * 86400
  );
};

const handlePay = (row: any) => {
  OrderApi.pay(row.id).then(() => {
    message("支付成功", { type: "success" });
    emit("refresh");
  });
};

const handleDownload = (row: any) => {
  OrderApi.download(row.id);
};

const handleView = (row: any) => {
  toDetail({ ids: row.id }, "params");
};

const handleRevalidate = (row: any) => {
  OrderApi.revalidate(row.id).then(() => {
    message("开始验证，请等待几分钟后刷新页面查看结果", { type: "success" });
    emit("refresh");
  });
};

const handleSync = (row: any) => {
  OrderApi.sync(row.id).then(() => {
    message("同步成功", { type: "success" });
    emit("refresh");
  });
};

const handleCommit = (row: any) => {
  OrderApi.commit(row.id).then(() => {
    message("提交成功", { type: "success" });
    emit("refresh");
  });
};

const handleCancel = async (row: any) => {
  if (
    await confirmOrderCancellation(
      [row.latest_cert?.status],
      cancellationMessage(row),
      () => OrderApi.commitCancel(row.id)
    )
  ) {
    message(
      ["unpaid", "pending"].includes(row.latest_cert?.status)
        ? "取消成功"
        : "取消申请已提交",
      { type: "success" }
    );
    emit("refresh");
  }
};
const handleRevokeCancel = async (row: any) => {
  await OrderApi.revokeCancel(row.id);
  message("撤回取消成功，正在同步订单状态", { type: "success" });
  emit("refresh");
};
</script>

<style scoped lang="scss">
.reset-margin {
  margin: 0;
}
</style>
