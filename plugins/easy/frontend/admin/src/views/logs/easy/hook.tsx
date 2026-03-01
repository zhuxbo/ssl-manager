import { message } from "../../../shared/message";
import type { PaginationProps } from "@pureadmin/table";
import { reactive, ref, onMounted, toRaw } from "vue";
import { useCopyToClipboard } from "@pureadmin/utils";
import { getEasyLogs, getLogDetail } from "../../../api/logs";
import { convertDateRangeToISO } from "../../../shared/utils";

export function useEasyLog() {
  const form = ref<any>({});
  const tableRef = ref();

  const dataList = ref<any[]>([]);
  const loading = ref(true);
  const { copied, update } = useCopyToClipboard();

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

  function handleCellDblclick({ url }: any, { property }: any) {
    if (property !== "url") return;
    update(url);
    copied.value
      ? message(`${url} 已拷贝`, { type: "success" })
      : message("拷贝失败", { type: "warning" });
  }

  const detailVisible = ref(false);
  const detailData = ref<any>(null);

  function onDetail(row: { id: number }) {
    getLogDetail(row.id)
      .then((res: any) => {
        detailData.value = res.data;
        detailVisible.value = true;
      })
      .catch(() => {
        message("获取详情失败", { type: "error" });
      });
  }

  function onSearch() {
    loading.value = true;
    const params: any = {
      ...toRaw(form.value),
      pageSize: pagination.pageSize,
      currentPage: pagination.currentPage
    };

    if (params.created_at) {
      params.created_at = convertDateRangeToISO(params.created_at);
    }

    getEasyLogs(params)
      .then(({ data }: any) => {
        dataList.value = data.items;
        pagination.total = data.total;
        pagination.pageSize = data.pageSize;
        pagination.currentPage = data.currentPage;
      })
      .finally(() => {
        loading.value = false;
      });
  }

  const onResetSearch = () => onSearch();

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
    detailVisible,
    detailData,
    onSearch,
    onDetail,
    onResetSearch,
    onCollapse,
    handleSizeChange,
    handleCurrentChange,
    handleCellDblclick
  };
}
