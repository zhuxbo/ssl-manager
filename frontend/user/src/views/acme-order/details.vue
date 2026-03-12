<template>
  <div class="main">
    <div v-if="order" class="layout-main bg-bg_color p-6">
      <el-descriptions title="订单信息" :column="3" border>
        <el-descriptions-item label="订单ID">{{
          order.id
        }}</el-descriptions-item>
        <el-descriptions-item label="品牌">{{
          order.brand
        }}</el-descriptions-item>
        <el-descriptions-item label="产品">{{
          order.product?.name || "-"
        }}</el-descriptions-item>
        <el-descriptions-item label="周期"
          >{{ order.period }} 天</el-descriptions-item
        >
        <el-descriptions-item label="金额">{{
          order.amount
        }}</el-descriptions-item>
        <el-descriptions-item label="验证方式">{{
          validationMethod[order.latest_cert?.validation_method] ||
          order.latest_cert?.validation_method ||
          "-"
        }}</el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag :type="statusType[order.latest_cert?.status] || 'info'">
            {{ status[order.latest_cert?.status] || order.latest_cert?.status }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="创建时间">{{
          formatDate(order.created_at)
        }}</el-descriptions-item>
        <el-descriptions-item label="有效期止">{{
          formatDate(order.period_till)
        }}</el-descriptions-item>
      </el-descriptions>

      <el-descriptions
        v-if="order.latest_cert"
        title="证书信息"
        :column="3"
        border
        class="mt-4"
      >
        <el-descriptions-item label="通用名称">{{
          order.latest_cert.common_name || "-"
        }}</el-descriptions-item>
        <el-descriptions-item label="备选名称">{{
          order.latest_cert.alternative_names || "-"
        }}</el-descriptions-item>
        <el-descriptions-item label="签发时间">{{
          formatDate(order.latest_cert.issued_at)
        }}</el-descriptions-item>
        <el-descriptions-item label="过期时间">{{
          formatDate(order.latest_cert.expires_at)
        }}</el-descriptions-item>
        <el-descriptions-item label="序列号">{{
          order.latest_cert.serial_number || "-"
        }}</el-descriptions-item>
        <el-descriptions-item label="签发者">{{
          order.latest_cert.issuer || "-"
        }}</el-descriptions-item>
      </el-descriptions>

      <el-descriptions
        v-if="authorizations.length > 0"
        title="域名验证"
        :column="1"
        border
        class="mt-4"
      >
        <el-descriptions-item
          v-for="authz in authorizations"
          :key="authz.id"
          :label="authz.identifier_value"
        >
          <el-tag
            :type="authz.status === 'valid' ? 'success' : 'primary'"
            class="mr-2"
          >
            {{ authz.status }}
          </el-tag>
          <span v-if="authz.challenges?.length" class="text-sm text-gray-500">
            {{ authz.challenges[0]?.type }} - {{ authz.challenges[0]?.status }}
          </span>
        </el-descriptions-item>
      </el-descriptions>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref, computed } from "vue";
import { getAcmeOrderDetail } from "@/api/acme";
import type { AcmeOrder } from "@/api/acme";
import { status, statusType, validationMethod } from "./dictionary";
import { useRoute } from "vue-router";
import dayjs from "dayjs";

defineOptions({
  name: "AcmeOrderDetails"
});

const order = ref<AcmeOrder | null>(null);

const authorizations = computed(() => {
  return order.value?.latest_cert?.acme_authorizations || [];
});

const formatDate = (date: string) => {
  return date ? dayjs(date).format("YYYY-MM-DD HH:mm:ss") : "-";
};

const route = useRoute();

const getDetails = () => {
  const ids = route.params.ids;
  if (ids) {
    getAcmeOrderDetail(Number(ids)).then(res => {
      order.value = res.data;
    });
  }
};

onMounted(() => {
  getDetails();
});
</script>

<style scoped lang="scss">
.layout-main {
  width: 100%;
  height: 100%;
  overflow: hidden;
}
</style>
