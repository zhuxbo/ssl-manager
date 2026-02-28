<script setup lang="tsx">
import { onMounted, ref, reactive, watch } from "vue";
import { PlusSearch } from "plus-pro-components";
import { useAgiso } from "./hook";
import { useAgisoSearch } from "./search";
import { useAgisoTable } from "./table";
import { useAgisoDetail } from "./detail";
import { payMethodOptions } from "./dictionary";
import { useRenderIcon } from "../../shared/ReIcon";
import CloseBold from "~icons/ep/close-bold";
import AddFill from "~icons/ri/add-circle-line";
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

// 创建订单
const createDialogVisible = ref(false);
const createLoading = ref(false);
const productList = ref<any[]>([]);
const periodOptions = ref<number[]>([]);
const createForm = reactive({
  product_code: "",
  period: undefined as number | undefined,
  amount: 0,
  pay_method: "other"
});
const createResult = ref<{
  tid: string;
  easy_url: string;
  recharge_url: string;
} | null>(null);

watch(
  () => createForm.product_code,
  code => {
    const product = productList.value.find((p: any) => p.code === code);
    periodOptions.value = product?.periods ?? [];
    createForm.period = periodOptions.value[0] ?? undefined;
  }
);

const openCreateDialog = async () => {
  createResult.value = null;
  createForm.product_code = "";
  createForm.period = undefined;
  createForm.amount = 0;
  createForm.pay_method = "other";
  createDialogVisible.value = true;
  try {
    const { data } = await agisoApi.products();
    productList.value = data;
  } catch {
    message("获取产品列表失败", { type: "error" });
  }
};

const handleCreate = async () => {
  if (!createForm.product_code) {
    message("请选择产品", { type: "warning" });
    return;
  }
  if (!createForm.period) {
    message("请选择周期", { type: "warning" });
    return;
  }
  createLoading.value = true;
  try {
    const { data } = await agisoApi.store({
      product_code: createForm.product_code,
      period: createForm.period,
      amount: createForm.amount,
      pay_method: createForm.pay_method
    });
    createResult.value = data;
    onSearch();
  } catch {
    message("创建订单失败", { type: "error" });
  } finally {
    createLoading.value = false;
  }
};

const copyText = async (text: string) => {
  try {
    await navigator.clipboard.writeText(text);
    message("已复制", { type: "success" });
  } catch {
    message("复制失败", { type: "error" });
  }
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
      <template #title>
        <el-button
          type="primary"
          :icon="useRenderIcon(AddFill)"
          @click="openCreateDialog"
        >
          创建订单
        </el-button>
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

    <el-dialog
      v-model="createDialogVisible"
      :title="createResult ? '创建成功' : '创建订单'"
      width="500px"
      destroy-on-close
    >
      <template v-if="!createResult">
        <el-form label-width="80px">
          <el-form-item label="产品">
            <el-select
              v-model="createForm.product_code"
              placeholder="请选择产品"
              filterable
              class="w-full"
            >
              <el-option
                v-for="p in productList"
                :key="p.code"
                :label="p.name"
                :value="p.code"
              />
            </el-select>
          </el-form-item>
          <el-form-item label="周期">
            <el-select
              v-model="createForm.period"
              placeholder="请选择周期"
              class="w-full"
            >
              <el-option
                v-for="p in periodOptions"
                :key="p"
                :label="`${p} 年`"
                :value="p"
              />
            </el-select>
          </el-form-item>
          <el-form-item label="金额">
            <el-input-number
              v-model="createForm.amount"
              :min="0"
              :precision="2"
              class="w-full"
            />
          </el-form-item>
          <el-form-item label="支付方式">
            <el-select v-model="createForm.pay_method" class="w-full">
              <el-option
                v-for="o in payMethodOptions"
                :key="o.value"
                :label="o.label"
                :value="o.value"
              />
            </el-select>
          </el-form-item>
        </el-form>
      </template>
      <template v-else>
        <el-form label-width="100px">
          <el-form-item label="简易申请链接">
            <el-input v-model="createResult.easy_url" readonly>
              <template #append>
                <el-button @click="copyText(createResult!.easy_url)">
                  复制
                </el-button>
              </template>
            </el-input>
          </el-form-item>
          <el-form-item label="充值链接">
            <el-input v-model="createResult.recharge_url" readonly>
              <template #append>
                <el-button @click="copyText(createResult!.recharge_url)">
                  复制
                </el-button>
              </template>
            </el-input>
          </el-form-item>
        </el-form>
      </template>
      <template #footer>
        <el-button v-if="!createResult" @click="createDialogVisible = false">
          取消
        </el-button>
        <el-button
          v-if="!createResult"
          type="primary"
          :loading="createLoading"
          @click="handleCreate"
        >
          创建
        </el-button>
        <el-button
          v-if="createResult"
          type="primary"
          @click="createDialogVisible = false"
        >
          关闭
        </el-button>
      </template>
    </el-dialog>
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
