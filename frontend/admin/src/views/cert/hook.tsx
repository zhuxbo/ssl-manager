import type { PaginationProps } from "@pureadmin/table";
import { reactive, ref, onMounted, toRaw } from "vue";
import { index, type IndexParams } from "@/api/cert";
import { convertDateRangeToISO } from "@/views/system/utils";

export function useCert() {
  const form = ref<IndexParams>({});

  const tableRef = ref();
  const dataList = ref([]);
  const loading = ref(true);

  const pagination = reactive<PaginationProps>({
    total: 0,
    pageSize: 10,
    currentPage: 1,
    background: true,
    pageSizes: [10, 20, 50, 100]
  });

  function handleSizeChange(val: number) {
    pagination.pageSize = val;
    onSearch();
  }

  function handleCurrentChange(val: number) {
    pagination.currentPage = val;
    onSearch();
  }

  function onSearch() {
    loading.value = true;
    const params = {
      ...toRaw(form.value),
      pageSize: pagination.pageSize,
      currentPage: pagination.currentPage
    };

    if (params.issued_at) {
      params.issued_at = convertDateRangeToISO(params.issued_at);
    }

    if (params.expires_at) {
      params.expires_at = convertDateRangeToISO(params.expires_at);
    }

    index(params)
      .then(({ data }) => {
        dataList.value = data.items;
        pagination.total = data.total;
        pagination.pageSize = data.pageSize;
        pagination.currentPage = data.currentPage;
      })
      .finally(() => {
        loading.value = false;
      });
  }

  const onReset = () => {
    onSearch();
  };

  const onCollapse = () => {
    setTimeout(() => {
      window.dispatchEvent(new Event("resize"));
      tableRef.value?.setAdaptive?.();
    }, 500);
  };

  onMounted(() => {
    onSearch();
  });

  return {
    tableRef,
    form,
    loading,
    dataList,
    pagination,
    onSearch,
    onReset,
    onCollapse,
    handleSizeChange,
    handleCurrentChange
  };
}
