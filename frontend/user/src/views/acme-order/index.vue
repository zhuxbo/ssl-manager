<script setup lang="tsx">
import { ref, onMounted, onBeforeUnmount } from "vue";
import { PlusSearch } from "plus-pro-components";
import { useAcmeOrder } from "./hook";
import { useAcmeOrderSearch } from "./search";
import { useAcmeOrderTable } from "./table";
import AcmeOrderButtons from "./buttons.vue";
import AcmeOrderCreate from "./create.vue";

defineOptions({
  name: "AcmeOrder"
});

const { tableColumns } = useAcmeOrderTable();

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
} = useAcmeOrder();

const { searchColumns } = useAcmeOrderSearch(onSearch);

const createVisible = ref(false);

type TimerRef = ReturnType<typeof setInterval>;
let searchTimer: TimerRef | null = null;

onMounted(() => {
  onSearch();
  searchTimer = setInterval(
    () => {
      onSearch();
    },
    3 * 60 * 1000
  );
});

onBeforeUnmount(() => {
  if (searchTimer !== null) {
    clearInterval(searchTimer);
    searchTimer = null;
  }
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
    <PureTableBar title="ACME订单" :columns="tableColumns" @refresh="onSearch">
      <template #buttons>
        <el-button type="primary" @click="createVisible = true"
          >创建订阅</el-button
        >
      </template>
      <template v-slot="{ size, dynamicColumns }">
        <pure-table
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
        >
          <template #operation="{ row, size }">
            <AcmeOrderButtons :row="row" :size="size" @refresh="onSearch" />
          </template>
        </pure-table>
      </template>
    </PureTableBar>

    <AcmeOrderCreate v-model:visible="createVisible" @success="onSearch" />
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
