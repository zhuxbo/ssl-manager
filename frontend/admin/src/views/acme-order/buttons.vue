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
    <el-button
      v-if="['processing'].includes(row.latest_cert?.status)"
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
    <el-popconfirm
      v-if="['active'].includes(row.latest_cert?.status)"
      title="确定要吊销吗？"
      width="160px"
      @confirm="handleRevoke(row)"
    >
      <template #reference>
        <el-button
          class="reset-margin !outline-none"
          type="danger"
          link
          :size="size"
        >
          吊销
        </el-button>
      </template>
    </el-popconfirm>
    <el-popconfirm
      v-if="allowDelete(row)"
      title="确定要删除吗？"
      width="160px"
      @confirm="handleDelete(row)"
    >
      <template #reference>
        <el-button
          class="reset-margin !outline-none"
          type="danger"
          link
          :size="size"
        >
          删除
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
  return ["processing", "approving", "active", "cancelling"].includes(
    row.latest_cert?.status
  );
};

const allowDelete = (row: AcmeOrder) => {
  return !row.latest_cert?.api_id;
};

const handleView = (row: AcmeOrder) => {
  router.push({ name: "AcmeOrderDetails", params: { ids: row.id } });
};

const handleRevalidate = (row: AcmeOrder) => {
  acmeApi.revalidateAcmeOrder(row.id).then(() => {
    message("开始验证，请等待几分钟后刷新页面查看结果", { type: "success" });
    emit("refresh");
  });
};

const handleSync = (row: AcmeOrder) => {
  acmeApi.syncAcmeOrder(row.id).then(() => {
    message("同步成功", { type: "success" });
    emit("refresh");
  });
};

const handleCancel = (row: AcmeOrder) => {
  acmeApi.cancelAcmeOrder(row.id).then(() => {
    message("取消成功", { type: "success" });
    emit("refresh");
  });
};

const handleRevoke = (row: AcmeOrder) => {
  acmeApi.revokeAcmeOrder(row.id).then(() => {
    message("吊销成功", { type: "success" });
    emit("refresh");
  });
};

const handleDelete = (row: AcmeOrder) => {
  acmeApi.deleteAcmeOrder(row.id).then(() => {
    message("删除成功", { type: "success" });
    emit("refresh");
  });
};
</script>

<style scoped lang="scss">
.reset-margin {
  margin: 0;
}
</style>
