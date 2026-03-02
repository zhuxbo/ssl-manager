<script setup lang="ts">
import { PlusSearch } from "plus-pro-components";
import { useEasyLog } from "./hook";
import { searchColumns } from "./search";
import { tableColumns } from "./table";
import { useRenderIcon } from "../../../shared/ReIcon";
import { useDrawerSize } from "../../../shared/utils";
import View from "~icons/ep/view";
import Detail from "./detail.vue";

defineOptions({
  name: "EasyLogs"
});

const { drawerSize } = useDrawerSize();

const {
  tableRef,
  form,
  loading,
  dataList,
  pagination,
  detailVisible,
  detailData,
  onSearch,
  onDetail,
  onResetSearch,
  onCollapse,
  handleSizeChange,
  handleCurrentChange,
  handleCellDblclick
} = useEasyLog();
</script>

<template>
  <div class="main">
    <div
      class="search bg-bg_color w-[99/100] pl-4 pr-4 pt-[24px] pb-[24px] overflow-auto"
    >
      <PlusSearch
        v-model="form"
        :columns="searchColumns"
        :show-number="2"
        :row-props="{ gutter: 12 }"
        :col-props="{ xs: 24, sm: 12, md: 8, lg: 6, xl: 4 }"
        label-width="80"
        label-position="right"
        label-suffix=""
        search-text="搜索"
        reset-text="重置"
        expand-text="展开"
        retract-text="收起"
        @search="onSearch"
        @reset="onResetSearch"
        @collapse="onCollapse"
      />
    </div>

    <PureTableBar
      title="简易申请日志"
      :columns="tableColumns"
      @refresh="onSearch"
    >
      <template v-slot="{ size, dynamicColumns }">
        <pure-table
          ref="tableRef"
          row-key="id"
          align-whole="left"
          table-layout="auto"
          :loading="loading"
          :size="size"
          adaptive
          :adaptiveConfig="{ offsetBottom: 108 }"
          :data="dataList"
          :columns="dynamicColumns"
          :pagination="{ ...pagination, size }"
          :header-cell-style="{
            background: 'var(--el-fill-color-light)',
            color: 'var(--el-text-color-primary)'
          }"
          @page-size-change="handleSizeChange"
          @page-current-change="handleCurrentChange"
          @cell-dblclick="handleCellDblclick"
        >
          <template #operation="{ row }">
            <el-button
              class="reset-margin !outline-none"
              link
              type="primary"
              :size="size"
              :icon="useRenderIcon(View)"
              @click="onDetail(row)"
            >
              详情
            </el-button>
          </template>
        </pure-table>
      </template>
    </PureTableBar>
    <el-drawer
      v-model="detailVisible"
      title="简易申请日志详情"
      direction="rtl"
      :size="drawerSize"
    >
      <Detail v-if="detailData" :data="detailData" />
    </el-drawer>
  </div>
</template>

<style scoped>
:deep(.el-dropdown-menu__item i) {
  margin: 0;
}

.main-content {
  margin: 24px 24px 0 !important;
}

.search-form :deep(.el-form-item) {
  margin-bottom: 12px;
}
</style>
