<template>
  <el-card shadow="never" :style="{ border: 'none' }">
    <h2 class="title">
      <span>颁发记录</span>
    </h2>
    <PureTable
      :columns="tableColumns"
      :data="tableData"
      :pagination="pagination"
      :header-cell-style="{
        background: 'var(--el-fill-color-light)',
        color: 'var(--el-text-color-primary)'
      }"
      @page-size-change="handleSizeChange"
      @page-current-change="handleCurrentChange"
    />
  </el-card>
</template>

<script setup lang="ts">
import { ref, reactive, inject, watch } from "vue";
import type { PaginationProps } from "@pureadmin/table";
import { PureTable } from "@pureadmin/table";
import * as CertApi from "@/api/cert";
import { tableColumns } from "../issueList";

const props = defineProps({
  activeTab: {
    type: String,
    required: true
  }
});

const order = inject("order") as any;

const tableData = ref([]);

const pagination = reactive<PaginationProps>({
  total: 0,
  pageSize: 10,
  currentPage: 1,
  background: true,
  pageSizes: [10, 20, 50, 100]
});

const loadData = async () => {
  if (props.activeTab !== "issueList") return;

  CertApi.index({
    currentPage: pagination.currentPage,
    pageSize: pagination.pageSize,
    order_id: order.id
  }).then(res => {
    if (res.code === 1) {
      tableData.value = res.data.items;
      pagination.total = res.data.total;
    }
  });
};

const handleSizeChange = (val: number) => {
  pagination.pageSize = val;
  loadData();
};

const handleCurrentChange = (val: number) => {
  pagination.currentPage = val;
  loadData();
};

watch(
  () => order.sync,
  () => {
    loadData();
  }
);

watch(
  () => props.activeTab,
  newVal => {
    if (newVal === "issueList") {
      loadData();
    }
  }
);
</script>

<style scoped lang="scss">
@import url("../../styles/detail.scss");

.id {
  padding: 0;
  font-family: Consolas, Monaco, serif;
  color: var(--el-color-primary);
  user-select: text;

  :hover {
    color: var(--el-color-primary);
  }
}
</style>
