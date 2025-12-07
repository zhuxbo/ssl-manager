import { ref } from "vue";
import dayjs from "dayjs";

export const useContactTable = () => {
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
      label: "姓",
      prop: "last_name",
      minWidth: 120
    },
    {
      label: "名",
      prop: "first_name",
      minWidth: 120
    },
    {
      label: "职位",
      prop: "title",
      minWidth: 120
    },
    {
      label: "邮箱",
      prop: "email",
      minWidth: 180
    },
    {
      label: "手机号",
      prop: "phone",
      minWidth: 120
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
