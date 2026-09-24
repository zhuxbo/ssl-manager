<template>
  <div v-if="props.placement === 'toolbar'" class="order-actions">
    <el-button size="small" @click="get(true)">刷新</el-button>
    <el-button
      v-if="['processing', 'active', 'approving'].includes(status)"
      size="small"
      @click="sync(true)"
      >同步</el-button
    >
    <el-button
      v-if="canReissue"
      size="small"
      @click="openAction('reissue', order.id)"
      >重签</el-button
    >
    <el-button
      v-if="canRenew"
      size="small"
      @click="openAction('renew', order.id)"
      >续费</el-button
    >
    <el-button
      v-if="['processing', 'active'].includes(status)"
      type="warning"
      plain
      size="small"
      @click="archive"
      >归档</el-button
    >
    <el-button
      v-if="allowCancel"
      type="danger"
      plain
      size="small"
      @click="commitCancel"
      >取消</el-button
    >
  </div>
  <el-button
    v-else-if="props.placement === 'commit' && status === 'pending'"
    type="primary"
    link
    size="small"
    @click="commit"
    >提交</el-button
  >
  <el-button
    v-else-if="props.placement === 'send' && status === 'active'"
    type="primary"
    link
    @click="sendEmailDialog = true"
    >发送邮件</el-button
  >
  <el-dialog v-model="sendEmailDialog" title="发送邮件">
    <el-form-item label="邮箱" :label-width="100">
      <el-input v-model="email" autocomplete="off" />
    </el-form-item>
    <template #footer>
      <span class="dialog-footer">
        <el-button @click="sendEmailDialog = false">{{ "取消" }}</el-button>
        <el-button type="primary" @click="send()">{{ "发送" }}</el-button>
      </span>
    </template>
  </el-dialog>
  <OrderAction
    v-model:visible="action.visible"
    :actionType="action.type"
    :orderId="action.id"
    @success="get(true)"
  />
</template>

<script setup lang="ts">
import { ref, inject, reactive, computed } from "vue";
import { buildUUID } from "@pureadmin/utils";
import router from "@/router";
import * as OrderApi from "@/api/order";
import { message } from "@shared/utils";
import { useOrderAction } from "@/views/order/action";
import OrderAction from "@/views/order/action.vue";
import { useMultiTagsStoreHook } from "@/store/modules/multiTags";
import { useRoute } from "vue-router";
import { useDetail } from "@/views/order/detail";
import dayjs from "dayjs";
import {
  cancellationMessage,
  confirmOrderAction,
  confirmOrderCancellation,
  archiveMessage
} from "@shared/utils/orderConfirmation";

const props = withDefaults(
  defineProps<{ placement?: "toolbar" | "send" | "transfer" | "commit" }>(),
  { placement: "toolbar" }
);

const { toDetail } = useDetail();
const route = useRoute();
const currentPath = route.path;
const params = route.params;

const order = inject("order") as any;
const sync = inject("sync") as Function;
const get = inject("get") as Function;

const status = computed(() => order.latest_cert?.status);
const allowCancel = computed(
  () =>
    ["unpaid", "pending"].includes(status.value) ||
    (["processing", "approving", "active"].includes(status.value) &&
      dayjs().diff(dayjs(order.created_at), "seconds") <=
        86400 * order.product.refund_period)
);
const supportsActions = computed(() =>
  ["ssl", "smime"].includes(order.product.product_type)
);
const withinPeriod = computed(
  () => !!order.period_till && dayjs(order.period_till).isAfter(dayjs())
);
const canReissue = computed(
  () =>
    supportsActions.value &&
    !!order.product.reissue &&
    withinPeriod.value &&
    ["active", "expired"].includes(status.value)
);
const canRenew = computed(
  () =>
    supportsActions.value &&
    !!order.product.renew &&
    !!order.product.status &&
    status.value === "active" &&
    withinPeriod.value &&
    dayjs(order.period_till).diff(dayjs(), "day") <= 30
);

// 打开操作抽屉
const { action, openAction } = useOrderAction();

const email = ref(order?.user?.email);
const sendEmailDialog = ref(false);
const send = () => {
  OrderApi.sendActive(order.id, email.value).then(() => {
    sendEmailDialog.value = false;
    message("发送成功", { type: "success" });
  });
};
const commit = () => {
  OrderApi.commit(order.id).then(() => {
    message("提交成功", { type: "success" });
    get();
  });
};
const commitCancel = async () => {
  const previousStatus = status.value;
  const confirmed = await confirmOrderCancellation(
    [previousStatus],
    cancellationMessage(order),
    () => OrderApi.commitCancel(order.id)
  );
  if (!confirmed) return;
  message(
    ["unpaid", "pending"].includes(previousStatus)
      ? "取消成功"
      : "取消申请已提交",
    { type: "success" }
  );
  if (previousStatus === "unpaid") {
    useMultiTagsStoreHook().handleTags("splice", currentPath);
    // 如果参数是多个id 则去除当前id再打开新的详情
    let ids = params.ids.toString().split(",");
    if (ids.length > 1) {
      // 从ids中去除当前id
      ids = ids.filter(id => id !== order.id.toString());
      toDetail({ ids: ids.join(",") }, "params");
    } else {
      router.push({ name: "Order" });
    }
  } else {
    OrderApi.show(order.id).then(res => {
      res.data.sync = buildUUID();
      Object.assign(order, reactive(res.data));
    });
  }
};

const archive = async () => {
  if (
    await confirmOrderAction("archive", archiveMessage, () =>
      OrderApi.archive(order.id)
    )
  ) {
    message("归档成功", { type: "success" });
    get(true);
  }
};
</script>

<style scoped lang="scss">
.order-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
  justify-content: flex-end;
  transform: translateY(2px);

  .el-button {
    margin: 0;
  }
}
</style>
