import { ref } from "vue";

export const useProductTable = (sourcesList: any) => {
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
      width: 50
    },
    {
      label: "来源",
      prop: "source",
      width: 80,
      formatter: (row: any) => {
        // 从sources列表中查找对应的标签
        const sourceItem = sourcesList.value?.find(
          (item: any) => item.value === row.source
        );
        return sourceItem
          ? sourceItem.label
          : row.source.charAt(0).toUpperCase() + row.source.slice(1);
      }
    },
    {
      label: "名称",
      prop: "name",
      minWidth: 150
    },
    {
      label: "说明",
      prop: "remark",
      minWidth: 150
    },
    {
      label: "权重",
      prop: "weight",
      width: 70
    },
    {
      label: "状态",
      prop: "status",
      width: 70,
      cellRenderer: ({ row, props }) => (
        <el-tag
          size={props.size}
          type={row.status === 1 ? "success" : "danger"}
          effect="plain"
        >
          {row.status === 1 ? "启用" : "禁用"}
        </el-tag>
      )
    },
    {
      label: "操作",
      fixed: "right",
      slot: "operation",
      width: 200
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
