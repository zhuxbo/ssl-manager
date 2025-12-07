import Detail from "./detail.vue";
import { addDialog } from "@shared/components/ReDialog";
import type { PaginationProps } from "@pureadmin/table";
import { reactive, ref, onMounted, toRaw } from "vue";
import { getAdminLogs, getLogDetail, type WebLogsParams } from "@/api/logs";
import { convertDateRangeToISO } from "@/views/system/utils";

export function useAdminLog() {
  const form = ref<WebLogsParams>({});

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

  function onDetail(row: { id: number }) {
    getLogDetail("admin", row.id).then(res => {
      addDialog({
        title: "管理员日志详情",
        fullscreen: true,
        hideFooter: true,
        contentRenderer: () => Detail,
        props: {
          data: res.data
        }
      });
    });
  }

  function onSearch() {
    loading.value = true;
    const params = {
      ...toRaw(form.value),
      pageSize: pagination.pageSize,
      currentPage: pagination.currentPage
    };

    if (params.created_at) {
      params.created_at = convertDateRangeToISO(params.created_at);
    }

    getAdminLogs(params)
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

  const onResetSearch = () => {
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
    onDetail,
    onResetSearch,
    onCollapse,
    handleSizeChange,
    handleCurrentChange
  };
}
