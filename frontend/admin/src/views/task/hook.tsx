import Detail from "./detail.vue";
import { addDialog } from "@shared/components/ReDialog";
import type { PaginationProps } from "@pureadmin/table";
import { reactive, ref, onMounted, toRaw, nextTick } from "vue";
import {
  index,
  show,
  destroy,
  batchDestroy,
  start,
  stop,
  execute,
  type IndexParams
} from "@/api/task";
import { message } from "@shared/utils";
import { convertDateRangeToISO } from "@/views/system/utils";

export function useTask() {
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

  function onDetail(row: { id: number }) {
    show(row.id).then(res => {
      addDialog({
        title: "任务详情",
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
    if (params.order_id) {
      params.order_id = Number(params.order_id);
    }

    index(params)
      .then(({ data }) => {
        dataList.value = data.items;
        pagination.total = data.total;
        pagination.pageSize = data.pageSize;
        pagination.currentPage = data.currentPage;

        nextTick(() => {
          tableRef.value && tableRef.value.getTableRef().clearSelection();
        });
      })
      .finally(() => {
        loading.value = false;
      });
  }

  const onResetSearch = () => {
    form.value = {};
    onSearch();
  };

  const onCollapse = () => {
    setTimeout(() => {
      window.dispatchEvent(new Event("resize"));
      tableRef.value?.setAdaptive?.();
    }, 500);
  };

  // 删除
  const onDelete = (row: { id: number }) => {
    loading.value = true;
    destroy(row.id)
      .then(() => {
        message("删除成功", { type: "success" });
        onSearch();
      })
      .finally(() => {
        loading.value = false;
      });
  };

  // 批量操作
  const onBatchStart = (ids: number[]) => {
    if (!ids || ids.length === 0) {
      message("请选择要操作的任务", { type: "warning" });
      return;
    }
    loading.value = true;
    start(ids)
      .then(() => {
        message("启动成功", { type: "success" });
        onSearch();
      })
      .finally(() => {
        loading.value = false;
      });
  };

  const onBatchStop = (ids: number[]) => {
    if (!ids || ids.length === 0) {
      message("请选择要操作的任务", { type: "warning" });
      return;
    }
    loading.value = true;
    stop(ids)
      .then(() => {
        message("停止成功", { type: "success" });
        onSearch();
      })
      .finally(() => {
        loading.value = false;
      });
  };

  const onBatchExecute = (ids: number[]) => {
    if (!ids || ids.length === 0) {
      message("请选择要操作的任务", { type: "warning" });
      return;
    }
    loading.value = true;
    execute(ids)
      .then(() => {
        message("执行成功", { type: "success" });
        onSearch();
      })
      .finally(() => {
        loading.value = false;
      });
  };

  const handleBatchDestroy = (ids: number[]) => {
    if (!ids || ids.length === 0) {
      message("请选择要删除的任务", { type: "warning" });
      return;
    }
    loading.value = true;
    batchDestroy(ids)
      .then(() => {
        message("删除成功", { type: "success" });
        onSearch();
      })
      .finally(() => {
        loading.value = false;
      });
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
    onResetSearch,
    onCollapse,
    onDelete,
    onDetail,
    onBatchStart,
    onBatchStop,
    onBatchExecute,
    handleBatchDestroy,
    handleSizeChange,
    handleCurrentChange
  };
}
