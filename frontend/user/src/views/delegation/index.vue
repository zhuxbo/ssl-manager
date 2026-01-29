<script setup lang="tsx">
import { onMounted, ref } from "vue";
import { PureTableBar } from "@shared/components";
import { PlusSearch, PlusDrawerForm } from "plus-pro-components";
import { useDelegation } from "./hook";
import { useDelegationSearch } from "./search";
import { useDelegationStore } from "./store";
import { useDelegationTable } from "./table";
import { useDrawerSize } from "@/views/system/drawer";
import { useRenderIcon } from "@shared/components/ReIcon/src/hooks";
import CloseBold from "~icons/ep/close-bold";
import CnameGuide from "@/views/delegation/CnameGuide/CnameGuide.vue";
import type { CnameGuideOptions } from "@/views/delegation/CnameGuide";

defineOptions({
  name: "Delegation"
});

// 使用统一的响应式抽屉宽度
const { drawerSize } = useDrawerSize();

// CNAME 配置指引对话框状态
const showCnameGuideDialog = ref(false);
const cnameGuideOptions = ref<CnameGuideOptions | null>(null);

// 显示 CNAME 配置指引
const handleShowCnameGuide = (options: CnameGuideOptions) => {
  cnameGuideOptions.value = options;
  showCnameGuideDialog.value = true;
};

const {
  tableRef,
  selectedIds,
  handleSelectionChange,
  handleSelectionCancel,
  tableColumns,
  handleRowClick
} = useDelegationTable();

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
  handleBatchDestroy,
  handleCheck,
  handleBatchCopy
} = useDelegation(tableRef, selectedIds);

// 创建搜索列配置
const { searchColumns } = useDelegationSearch(() => onSearch());

// 创建表单列配置
const {
  storeRef,
  showStore,
  storeId,
  storeColumns,
  rules,
  storeValues,
  openStoreForm,
  confirmStoreForm,
  closeStoreForm,
  showBatchStore,
  batchStoreValues,
  batchStoreColumns,
  batchStoreRules,
  openBatchStoreForm,
  confirmBatchStoreForm,
  closeBatchStoreForm
} = useDelegationStore(() => onSearch(), handleShowCnameGuide);

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
        :show-number="1"
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
    <PureTableBar
      title="CNAME 委托管理"
      :columns="tableColumns"
      @refresh="onSearch"
    >
      <template #buttons>
        <el-button type="primary" @click="openStoreForm()">
          新建委托
        </el-button>
        <el-button @click="openBatchStoreForm()"> 批量委托 </el-button>
      </template>
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
          <el-button type="primary" size="small" @click="handleBatchCopy">
            批量复制
          </el-button>
          <el-popconfirm
            title="确定要删除吗？删除后将不再自动写入 TXT 记录"
            width="220px"
            @confirm="handleBatchDestroy(selectedIds)"
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
          :pagination="{ ...pagination, size }"
          :header-cell-style="{
            background: 'var(--el-fill-color-light)',
            color: 'var(--el-text-color-primary)'
          }"
          @row-click="handleRowClick"
          @selection-change="handleSelectionChange"
          @page-size-change="handleSizeChange"
          @page-current-change="handleCurrentChange"
        >
          <template #operation="{ row }">
            <el-button
              class="reset-margin !outline-none"
              type="primary"
              link
              :size="size"
              @click="handleShowCnameGuide(row)"
            >
              配置指引
            </el-button>
            <el-button
              class="reset-margin !outline-none"
              type="primary"
              link
              :size="size"
              @click="handleCheck(row.id)"
            >
              检查
            </el-button>
            <el-popconfirm
              title="确定要删除吗？删除后将不再自动写入 TXT 记录"
              width="220px"
              @confirm="handleDestroy(row.id)"
            >
              <template #reference>
                <el-button
                  class="reset-margin !outline-none"
                  link
                  type="danger"
                  :size="size"
                >
                  删除
                </el-button>
              </template>
            </el-popconfirm>
          </template>
        </pure-table>
      </template>
    </PureTableBar>
    <PlusDrawerForm
      ref="storeRef"
      v-model="storeValues"
      :visible="showStore"
      :form="{
        columns: storeColumns,
        rules,
        labelPosition: 'right',
        labelSuffix: ''
      }"
      :size="drawerSize"
      :closeOnClickModal="true"
      :title="storeId > 0 ? '编辑委托' : '新建委托'"
      confirmText="提交"
      cancelText="取消"
      @confirm="confirmStoreForm"
      @cancel="closeStoreForm"
    />
    <PlusDrawerForm
      v-model="batchStoreValues"
      :visible="showBatchStore"
      :form="{
        columns: batchStoreColumns,
        rules: batchStoreRules,
        labelPosition: 'right',
        labelSuffix: ''
      }"
      :size="drawerSize"
      :closeOnClickModal="true"
      title="批量委托"
      confirmText="提交"
      cancelText="取消"
      @confirm="confirmBatchStoreForm"
      @cancel="closeBatchStoreForm"
    />
    <!-- CNAME 配置指引对话框 -->
    <CnameGuide v-model="showCnameGuideDialog" :options="cnameGuideOptions" />
  </div>
</template>

<style scoped lang="scss">
:deep(.el-dropdown-menu__item i) {
  margin: 0;
}

.main-content {
  margin: 24px 24px 0 !important;
}

.search {
  :deep(.el-form-item) {
    margin-bottom: 12px;
  }
}
</style>
