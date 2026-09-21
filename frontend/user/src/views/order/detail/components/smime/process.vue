<template>
  <el-card shadow="never" :style="{ border: 'none' }">
    <div class="title order-status-header">
      <div class="order-status-summary">
        <h2>订单状态</h2>
        <span
          class="order-status-text"
          :style="{
            color: `var(--el-color-${statusType[cert?.status] || 'info'})`
          }"
          >{{ status[cert?.status] }}</span
        >
      </div>
      <Operate />
    </div>
    <table class="descriptions" style="width: 100%">
      <tbody>
        <tr>
          <td class="label">
            <el-icon :size="16" class="icon" :color="commitColor">
              <Select />
            </el-icon>
          </td>
          <td class="content">提交订单 <Operate placement="commit" /></td>
        </tr>
        <tr v-if="order.product.validation_type !== 'dv'">
          <td class="label">
            <el-icon :size="16" class="icon" :color="orgValidationColor">
              <Select />
            </el-icon>
          </td>
          <td class="content">企业验证</td>
        </tr>
        <tr
          v-if="
            order.product.validation_type !== 'dv' &&
            order.product.ca?.toLowerCase() === 'certum' &&
            cert?.status === 'processing'
          "
        >
          <td class="label" />
          <td class="content">
            <Documents v-if="hasDocuments" />
            <DocumentUpload />
          </td>
        </tr>
        <tr>
          <td class="label">
            <el-icon :size="16" class="icon" :color="validationColor">
              <Select />
            </el-icon>
          </td>
          <td class="content">邮箱验证</td>
        </tr>
        <tr>
          <td class="label" />
          <td class="content"><SmimeValidation /></td>
        </tr>
        <tr>
          <td class="label">
            <el-icon :size="16" class="icon" :color="issuedColor">
              <Select />
            </el-icon>
          </td>
          <td class="content">下载证书</td>
        </tr>
        <tr v-if="cert.status === 'active'">
          <td class="label" />
          <td class="content"><SmimeInstall /><Operate placement="send" /></td>
        </tr>
      </tbody>
    </table>
  </el-card>
</template>

<script setup lang="ts">
import { computed, inject } from "vue";
import { statusType, status } from "@/views/order/dictionary";
import Operate from "../operate.vue";
import SmimeValidation from "./validation.vue";
import SmimeInstall from "./install.vue";
import Documents from "../documents.vue";
import DocumentUpload from "../documentUpload.vue";
import { Select } from "@element-plus/icons-vue";

const order = inject("order") as any;
const cert = inject("cert") as any;

const hasDocuments = computed(() => {
  const docs = cert.value?.documents;
  if (!docs) return false;
  return Array.isArray(docs) ? docs.length > 0 : true;
});

const getStatusColor = (status: string) => {
  return computed(() => {
    if (cert.value.status === "active") {
      return "var(--el-color-success)";
    }
    if (cert.value.status == "processing") {
      return cert.value[status] == 2
        ? "var(--el-color-success)"
        : "var(--el-text-color-regular)";
    }
    return "var(--el-text-color-regular)";
  });
};

const commitColor = getStatusColor("cert_apply_status");
const orgValidationColor = getStatusColor("org_verify_status");
const validationColor = getStatusColor("domain_verify_status");
const issuedColor = computed(() =>
  cert.value.status === "active"
    ? "var(--el-color-success)"
    : "var(--el-text-color-regular)"
);
</script>

<style scoped lang="scss">
@import url("../../styles/detail.scss");

.label {
  width: 35px;
  margin-right: 5px;

  .icon {
    margin-top: 5px;
  }
}

.content {
  width: calc(100% - 40px);
}

.hint {
  font-size: 12px;
  color: var(--el-text-color-placeholder);
}
</style>
