<template>
  <div class="flex items-center gap-2">
    <el-button
      v-if="row.status === 'unpaid'"
      class="reset-margin !outline-none"
      type="primary"
      link
      :size="size"
      @click="handlePay(row)"
    >
      支付
    </el-button>
    <el-button
      v-if="row.status === 'pending'"
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
    <el-popconfirm
      v-if="allowCancel(row)"
      title="确定要取消吗？"
      width="160px"
      @confirm="handleCancel(row)"
    >
      <template #reference>
        <el-button
          class="reset-margin !outline-none"
          type="danger"
          link
          :size="size"
        >
          取消
        </el-button>
      </template>
    </el-popconfirm>
  </div>
</template>

<script setup lang="ts">
import * as acmeApi from "@/api/acme";
import type { Acme } from "@/api/acme";
import { message } from "@shared/utils";
import { useRouter } from "vue-router";

const router = useRouter();

const emit = defineEmits<{
  (e: "refresh"): void;
}>();

defineProps<{
  row: Acme;
  size?: "default" | "small" | "large";
}>();

const allowCancel = (row: Acme) => {
  return ["pending", "active"].includes(row.status);
};

const handlePay = (row: Acme) => {
  acmeApi.payOrder(row.id).then(() => {
    message("支付成功", { type: "success" });
    emit("refresh");
  });
};

const handleCommit = (row: Acme) => {
  acmeApi.commitOrder(row.id).then(() => {
    message("提交成功", { type: "success" });
    emit("refresh");
  });
};

const handleView = (row: Acme) => {
  router.push({ name: "AcmeDetails", params: { ids: row.id } });
};

const handleCancel = (row: Acme) => {
  acmeApi.cancelAcme(row.id).then(() => {
    message("取消成功", { type: "success" });
    emit("refresh");
  });
};
</script>

<style scoped lang="scss">
.reset-margin {
  margin: 0;
}
</style>
