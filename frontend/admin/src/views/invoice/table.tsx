import { ref } from "vue";
import dayjs from "dayjs";
import { createUsernameRenderer } from "@/views/system/username";

export function useInvoiceTable() {
  const tableRef = ref();
  const selectedIds = ref([]);

  const handleSelectionChange = (val: any) => {
    selectedIds.value = val.map((row: any) => row.id);
    // 重置表格高度
    tableRef.value.setAdaptive();
  };

  const handleSelectionCancel = () => {
    tableRef.value?.getTableRef().clearSelection();
  };

  const statusMap = {
    0: { label: "处理中", type: "primary" },
    1: { label: "已开票", type: "success" },
    2: { label: "已作废", type: "danger" }
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
      width: 130
    },
    {
      label: "用户名",
      prop: "user.username",
      width: 100,
      cellRenderer: createUsernameRenderer("user.username")
    },
    {
      label: "金额",
      prop: "amount",
      width: 100
    },
    {
      label: "组织",
      prop: "organization",
      minWidth: 150
    },
    {
      label: "备注",
      prop: "remark",
      minWidth: 150
    },
    {
      label: "状态",
      prop: "status",
      width: 100,
      cellRenderer: ({ row, props }) => (
        <el-tag
          size={props.size}
          type={statusMap[row.status]?.type}
          effect="plain"
        >
          {statusMap[row.status]?.label}
        </el-tag>
      )
    },
    {
      label: "创建时间",
      prop: "created_at",
      width: 160,
      formatter: ({ created_at }) => {
        return created_at
          ? dayjs(created_at).format("YYYY-MM-DD HH:mm:ss")
          : "-";
      }
    },
    {
      label: "操作",
      fixed: "right",
      width: 110,
      slot: "operation"
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
    tableColumns,
    handleSelectionChange,
    handleSelectionCancel,
    handleRowClick
  };
}
