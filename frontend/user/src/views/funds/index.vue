<script setup lang="tsx">
import { onMounted } from "vue";
import { PureTableBar } from "@shared/components";
import { PlusSearch } from "plus-pro-components";
import { useFunds } from "./hook";
import { useFundsSearch } from "./search";
import { useFundsTable } from "./table";
import { topUpDialogStore } from "@/store/modules/topUp";
import { platformRecharge } from "@/api/funds";
import { useRoute, useRouter } from "vue-router";
import { message } from "@shared/utils";

const route = useRoute();
const router = useRouter();

defineOptions({
  name: "Funds"
});

const { tableRef, tableColumns } = useFundsTable();

const {
  loading,
  search,
  dataList,
  pagination,
  handleSizeChange,
  handleCurrentChange,
  onSearch,
  onReset,
  onCollapse
} = useFunds(tableRef);

// 创建搜索列配置
const { searchColumns } = useFundsSearch(() => onSearch());

async function addfundsFromTid() {
  const tid = route.query.tid as string;
  if (tid) {
    return await platformRecharge(tid).then(() => {
      topUpDialogStore().updateBalance();
      message("充值成功", { type: "success" });
      router.push("/funds");
    });
  } else {
    return Promise.resolve();
  }
}

onMounted(() => {
  addfundsFromTid();
  if (route.query.id) {
    search.value.id = Number(route.query.id);
  }
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
    <PureTableBar :columns="tableColumns" @refresh="onSearch">
      <template #title>
        <el-button type="success" @click="topUpDialogStore().showDialog">
          充值
        </el-button>
      </template>
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
        />
      </template>
    </PureTableBar>
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
