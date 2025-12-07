import Detail from "./detail.vue";
import { message } from "@shared/utils";
import { addDialog } from "@shared/components/ReDialog";
import type { PaginationProps } from "@pureadmin/table";
import { reactive, ref, onMounted, toRaw } from "vue";
import { useCopyToClipboard } from "@pureadmin/utils";
import { getCaLogs, getLogDetail, type CaLogsParams } from "@/api/logs";
import { convertDateRangeToISO } from "@/views/system/utils";

export function useCaLog() {
  const form = ref<CaLogsParams>({});
  const tableRef = ref();

  const dataList = ref([]);
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

  /** 拷贝请求URL，表格单元格被双击时触发 */
  function handleCellDblclick({ url }, { property }) {
    if (property !== "url") return;
    update(url);
    copied.value
      ? message(`${url} 已拷贝`, { type: "success" })
      : message("拷贝失败", { type: "warning" });
  }

  function onDetail(row: { id: number }) {
    getLogDetail("ca", row.id).then(res => {
      addDialog({
        title: "CA日志详情",
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

    getCaLogs(params)
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
    handleCurrentChange,
    handleCellDblclick
  };
}
