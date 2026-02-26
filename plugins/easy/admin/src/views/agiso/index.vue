<script setup lang="tsx">
import { onMounted } from "vue";
import { PlusSearch } from "plus-pro-components";
import { useAgiso } from "./hook";
import { useAgisoSearch } from "./search";
import { useAgisoTable } from "./table";
import { useAgisoDetail } from "./detail";
import { useRenderIcon } from "../../shared/ReIcon";
import CloseBold from "~icons/ep/close-bold";
import PureDescriptions from "@pureadmin/descriptions";
import "vue-json-pretty/lib/styles.css";
import VueJsonPretty from "vue-json-pretty";
import * as agisoApi from "../../api/agiso";
import { message } from "../../shared/message";
import { useDrawerSize } from "../../shared/utils";

defineOptions({
  name: "Agiso"
});

const { drawerSize } = useDrawerSize();

const {
  tableRef,
  selectedIds,
  handleSelectionChange,
  handleSelectionCancel,
  tableColumns,
  handleRowClick
} = useAgisoTable();

const {
  loading,
  search,
  dataList,
  pagination,
  handleSizeChange,
  handleCurrentChange,
  onSearch,
  onReset,
  onCollapse,
  handleDestroy,
  handleBatchDestroy
} = useAgiso(tableRef);

const { searchColumns, debouncedSearch } = useAgisoSearch(() => onSearch());

const {
  showDrawer,
  detailData,
  columns,
  dataList: detailDataList,
  openDrawer,
  closeDrawer
} = useAgisoDetail();

const handleShowDetail = async (row: any) => {
  try {
    const { data } = await agisoApi.show(row.id);
    openDrawer(data);
  } catch (error) {
    message("获取详情失败", { type: "error" });
  }
};

const handleDeleteRow = (row: any) => {
  if (row.recharged === 1) {
    message("已充值的记录不能删除", { type: "warning" });
    return;
  }
  handleDestroy(row.id);
};

const handleBatchDeleteRows = () => {
  if (selectedIds.value.length === 0) return;

  const selectedRows = dataList.value.filter((row: any) =>
    selectedIds.value.includes(row.id)
  );
  const rechargedRows = selectedRows.filter((row: any) => row.recharged === 1);

  if (rechargedRows.length > 0) {
    message("选中的记录中包含已充值的记录，不能删除", { type: "warning" });
    return;
  }

  handleBatchDestroy(selectedIds.value);
};

onMounted(() => {
  onSearch();
});
</script>

<template>
  <div class="main">
    <div
      class="search bg-bg_color w-[99/100] pl-4 pr-4 pt-[24px] pb-[12px] overflow-auto"
    >
      <PlusSearch
        v-model="search"
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
        @reset="onReset"
        @collapse="onCollapse"
      />
    </div>
    <PureTableBar title="电商平台" :columns="tableColumns" @refresh="onSearch">
      <template v-slot="{ size, dynamicColumns }">
        <div
          v-if="selectedIds.length > 0"
          v-motion-fade
          class="bg-[var(--el-fill-color-light)] w-full h-[46px] mb-2 pl-3 pr-2 flex items-center"
        >
          <div class="flex-auto">
            <el-tooltip placement="top" content="取消选择">
              <el-button
                type="primary"
                size="small"
                class="!w-[15px] !p-0 !h-[15px] !rounded-[3px]"
                :icon="useRenderIcon(CloseBold)"
                @click="handleSelectionCancel"
              />
            </el-tooltip>
            <span
              style="font-size: var(--el-font-size-base)"
              class="text-[rgba(42,46,54,0.5)] dark:text-[rgba(220,220,242,0.5)] ml-2"
            >
              已选 {{ selectedIds.length }} 项
            </span>
          </div>
          <el-popconfirm
            title="确定要删除吗？"
            width="160px"
            @confirm="handleBatchDeleteRows"
          >
            <template #reference>
              <el-button type="danger" size="small"> 批量删除 </el-button>
            </template>
          </el-popconfirm>
        </div>
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
          :pagination="pagination"
          :paginationSmall="size === 'small' ? true : false"
          :header-cell-style="{
            background: 'var(--el-fill-color-light)',
            color: 'var(--el-text-color-primary)'
          }"
          @selection-change="handleSelectionChange"
          @page-size-change="handleSizeChange"
          @page-current-change="handleCurrentChange"
          @row-click="handleRowClick"
        >
          <template #operation="{ row }">
            <el-button
              class="reset-margin"
              link
              type="primary"
              size="small"
              @click="handleShowDetail(row)"
            >
              详情
            </el-button>
            <el-popconfirm
              :title="`确认删除记录 ${row.tid} 吗？`"
              @confirm="handleDeleteRow(row)"
            >
              <template #reference>
                <el-button
                  class="reset-margin"
                  link
                  type="danger"
                  size="small"
                  :disabled="row.recharged === 1"
                >
                  删除
                </el-button>
              </template>
            </el-popconfirm>
          </template>
        </pure-table>
      </template>
    </PureTableBar>

    <el-drawer
      v-model="showDrawer"
      title="交易详情"
      direction="rtl"
      :size="drawerSize"
      :before-close="closeDrawer"
    >
      <div v-if="detailData">
        <el-scrollbar>
          <PureDescriptions
            border
            :data="[detailData]"
            :columns="columns"
            :column="2"
          />
        </el-scrollbar>
        <el-tabs
          :model-value="detailDataList[0]?.name"
          type="border-card"
          class="mt-4"
        >
          <el-tab-pane
            v-for="(item, index) in detailDataList"
            :key="index"
            :name="item.name"
            :label="item.title"
          >
            <el-scrollbar max-height="calc(100vh - 300px)">
              <vue-json-pretty :data="item.data" />
            </el-scrollbar>
          </el-tab-pane>
        </el-tabs>
      </div>
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

.search :deep(.el-form-item) {
  margin-bottom: 12px;
}
</style>
