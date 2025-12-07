<template>
  <div class="documents">
    <el-table
      v-if="documents.length > 0"
      :data="documents"
      size="small"
      :style="{ width: '100%' }"
    >
      <el-table-column prop="type" label="类型" width="140">
        <template #default="{ row }">
          {{ documentTypes[row.type] || row.type }}
        </template>
      </el-table-column>
      <el-table-column prop="state" label="状态" width="80">
        <template #default="{ row }">
          <el-tag :type="getTagType(row.state)" size="small">
            {{ documentStates[row.state] || row.state }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="createDate" label="上传时间" width="160">
        <template #default="{ row }">
          {{ formatDate(row.createDate) }}
        </template>
      </el-table-column>
      <el-table-column prop="expireDate" label="过期时间" width="160">
        <template #default="{ row }">
          {{ formatDate(row.expireDate) }}
        </template>
      </el-table-column>
    </el-table>
    <el-empty v-else description="暂无验证文档" :image-size="60" />
  </div>
</template>

<script setup lang="ts">
import { inject, computed } from "vue";
import type { EpPropMergeType } from "element-plus/es/utils/vue/props/types";

const cert = inject("cert") as any;

// 文档类型（根据 Certum API 文档）
const documentTypes: Record<string, string> = {
  APPLICANT: "申请人文档",
  ORGANIZATION: "企业文档",
  AUTHORIZATION: "授权文档",
  ADDITIONAL: "附加文档",
  VERIFICATION_REPORT: "验证报告",
  ATTESTATION_LETTER: "证明函"
};

// 文档状态
const documentStates: Record<string, string> = {
  NEW: "待审核",
  ACCEPTED: "已通过",
  REJECTED: "已拒绝"
};

// 状态标签颜色（使用 Element Plus 的类型工具）
type TagType = EpPropMergeType<
  StringConstructor,
  "info" | "primary" | "success" | "warning" | "danger",
  unknown
>;
const stateTagType: Record<string, TagType> = {
  NEW: "warning",
  ACCEPTED: "success",
  REJECTED: "danger"
};

const getTagType = (state: string): TagType => {
  return stateTagType[state] || "info";
};

const documents = computed(() => {
  const docs = cert.value?.documents;
  if (!docs) return [];
  // 单个文档时 Certum API 返回对象，需要转换为数组
  return Array.isArray(docs) ? docs : [docs];
});

const formatDate = (dateStr: string) => {
  if (!dateStr) return "";
  const date = new Date(dateStr);
  return date.toLocaleString("zh-CN", {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit"
  });
};
</script>

<style scoped lang="scss">
.documents {
  margin: 10px 0;

  :deep(.el-table) {
    margin-bottom: 15px;

    // 对齐表格边框
    &::before {
      display: none;
    }

    .el-table__inner-wrapper::before {
      display: none;
    }

    th.el-table__cell,
    td.el-table__cell {
      border-bottom: 1px solid var(--el-border-color-lighter);
    }

    .el-table__header th {
      background-color: var(--el-fill-color-light);
    }
  }
}
</style>
