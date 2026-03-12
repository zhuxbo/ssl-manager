<template>
  <div class="flex items-center gap-2">
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
import type { AcmeOrder } from "@/api/acme";
import { message } from "@shared/utils";
import { useRouter } from "vue-router";

const router = useRouter();

const emit = defineEmits<{
  (e: "refresh"): void;
}>();

defineProps<{
  row: AcmeOrder;
  size?: "default" | "small" | "large";
}>();

const allowCancel = (row: AcmeOrder) => {
  return ["processing", "approving", "active"].includes(
    row.latest_cert?.status
  );
};

const handleView = (row: AcmeOrder) => {
  router.push({ name: "AcmeOrderDetails", params: { ids: row.id } });
};

const handleCancel = (row: AcmeOrder) => {
  acmeApi.cancelAcmeOrder(row.id).then(() => {
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
