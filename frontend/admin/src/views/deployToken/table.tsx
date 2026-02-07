import { ref } from "vue";
import dayjs from "dayjs";
import { createUsernameRenderer } from "@/views/system/username";

export const useDeployTokenTable = () => {
  const tableRef = ref();
  const selectedIds = ref<number[]>([]);

  const handleSelectionChange = (val: any) => {
    selectedIds.value = val.map((row: any) => row.id);
    // 重置表格高度
    tableRef.value.setAdaptive();
  };

  const handleSelectionCancel = () => {
    tableRef.value?.getTableRef().clearSelection();
  };

  const tableColumns: TableColumnList = [
    {
      label: "勾选列",
      type: "selection",
      reserveSelection: true
    },
    {
      label: "ID",
      prop: "id",
      minWidth: 80
    },
    {
      label: "用户名",
      prop: "user.username",
      minWidth: 120,
      cellRenderer: createUsernameRenderer("user.username")
    },
    {
      label: "IP白名单",
      prop: "allowed_ips",
      minWidth: 200
    },
    {
      label: "频率限制",
      prop: "rate_limit",
      minWidth: 100,
      formatter: ({ rate_limit }) => {
        return `${rate_limit}/分钟`;
      }
    },
    {
      label: "最后调用时间",
      prop: "last_used_at",
      minWidth: 100,
      formatter: ({ last_used_at }) => {
        return last_used_at
          ? dayjs(last_used_at).format("YYYY-MM-DD HH:mm:ss")
          : "从未调用";
      }
    },
    {
      label: "最后调用IP",
      prop: "last_used_ip",
      minWidth: 100,
      formatter: ({ last_used_ip }) => {
        return last_used_ip || "从未调用";
      }
    },
    {
      label: "状态",
      prop: "status",
      minWidth: 80,
      formatter: ({ status }) => {
        return status === 1 ? "启用" : "禁用";
      }
    },
    {
      label: "创建时间",
      prop: "created_at",
      minWidth: 180,
      formatter: ({ created_at }) =>
        created_at ? dayjs(created_at).format("YYYY-MM-DD HH:mm:ss") : "-"
    },
    {
      label: "操作",
      fixed: "right",
      slot: "operation",
      width: 110
    }
  ];

  const handleRowClick = (row: any, _column: any, event: any) => {
    // 通过事件目标判断是否点击了按钮
    const target = event.target as HTMLElement;
    if (
      target.tagName === "BUTTON" ||
      target.closest("button") ||
      target.closest(".el-button")
    ) {
      return;
    }
    // 切换当前行的选中状态
    tableRef.value?.getTableRef().toggleRowSelection(row);
  };

  return {
    tableRef,
    selectedIds,
    handleSelectionChange,
    handleSelectionCancel,
    tableColumns,
    handleRowClick
  };
};
