<template>
  <div class="main">
    <div v-if="acme" class="layout-main bg-bg_color p-6">
      <el-descriptions title="订单信息" :column="3" border>
        <el-descriptions-item label="订单ID">{{
          acme.id
        }}</el-descriptions-item>
        <el-descriptions-item label="品牌">{{
          acme.brand
        }}</el-descriptions-item>
        <el-descriptions-item label="产品">{{
          acme.product?.name || "-"
        }}</el-descriptions-item>
        <el-descriptions-item label="用户">{{
          acme.user?.username || "-"
        }}</el-descriptions-item>
        <el-descriptions-item label="周期"
          >{{ acme.period }} 个月</el-descriptions-item
        >
        <el-descriptions-item label="金额">{{
          acme.amount
        }}</el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag :type="statusType[acme.status] || 'info'">
            {{ status[acme.status] || acme.status }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="标准域名额度">{{
          acme.purchased_standard_count
        }}</el-descriptions-item>
        <el-descriptions-item label="通配符域名额度">{{
          acme.purchased_wildcard_count
        }}</el-descriptions-item>
        <el-descriptions-item label="创建时间">{{
          formatDate(acme.created_at)
        }}</el-descriptions-item>
        <el-descriptions-item label="有效期起">{{
          formatDate(acme.period_from)
        }}</el-descriptions-item>
        <el-descriptions-item label="有效期止">{{
          formatDate(acme.period_till)
        }}</el-descriptions-item>
        <el-descriptions-item label="取消时间">{{
          formatDate(acme.cancelled_at)
        }}</el-descriptions-item>
      </el-descriptions>

      <el-descriptions title="EAB 凭据" :column="2" border class="mt-4">
        <el-descriptions-item label="EAB KID">
          <div class="flex items-center gap-2">
            <span>{{ acme.eab_kid || "-" }}</span>
            <el-button
              v-if="acme.eab_kid"
              link
              size="small"
              @click="handleCopy(acme.eab_kid)"
            >
              <el-icon size="14"><DocumentCopy /></el-icon>
            </el-button>
          </div>
        </el-descriptions-item>
        <el-descriptions-item label="EAB HMAC">
          <div class="flex items-center gap-2">
            <span>{{ acme.eab_hmac || "-" }}</span>
            <el-button
              v-if="acme.eab_hmac"
              link
              size="small"
              @click="handleCopy(acme.eab_hmac)"
            >
              <el-icon size="14"><DocumentCopy /></el-icon>
            </el-button>
          </div>
        </el-descriptions-item>
      </el-descriptions>

      <div class="mt-4">
        <el-descriptions title="备注" :column="1" border>
          <el-descriptions-item v-if="acme.remark" label="备注">{{
            acme.remark
          }}</el-descriptions-item>
          <el-descriptions-item label="管理员备注">
            <div class="flex items-center gap-2">
              <span>{{ acme.admin_remark || "-" }}</span>
              <el-button link size="small" @click="remarkDialogVisible = true">
                编辑
              </el-button>
            </div>
          </el-descriptions-item>
        </el-descriptions>
      </div>

      <el-dialog
        v-model="remarkDialogVisible"
        title="编辑管理员备注"
        width="500"
      >
        <el-input
          v-model="remarkInput"
          type="textarea"
          :rows="3"
          maxlength="255"
          show-word-limit
        />
        <template #footer>
          <el-button @click="remarkDialogVisible = false">取消</el-button>
          <el-button type="primary" @click="handleRemark">确定</el-button>
        </template>
      </el-dialog>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from "vue";
import { getAcmeDetail, remarkAcme } from "@/api/acme";
import type { Acme } from "@/api/acme";
import { status, statusType } from "./dictionary";
import { useRoute } from "vue-router";
import { message } from "@shared/utils";
import { DocumentCopy } from "@element-plus/icons-vue";
import dayjs from "dayjs";

defineOptions({
  name: "AcmeDetails"
});

const acme = ref<Acme | null>(null);
const remarkDialogVisible = ref(false);
const remarkInput = ref("");

const formatDate = (date: string | null) => {
  return date ? dayjs(date).format("YYYY-MM-DD HH:mm:ss") : "-";
};

const handleCopy = (text: string) => {
  navigator.clipboard
    .writeText(text)
    .then(() => {
      message("已复制到剪贴板", { type: "success" });
    })
    .catch(() => {
      message("复制失败，请手动复制", { type: "error" });
    });
};

const handleRemark = () => {
  if (!acme.value) return;
  remarkAcme(acme.value.id, remarkInput.value).then(() => {
    message("备注已更新", { type: "success" });
    remarkDialogVisible.value = false;
    getDetails();
  });
};

const route = useRoute();

const getDetails = () => {
  const ids = route.params.ids;
  if (ids) {
    getAcmeDetail(Number(ids)).then(res => {
      acme.value = res.data;
      remarkInput.value = res.data?.admin_remark || "";
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
