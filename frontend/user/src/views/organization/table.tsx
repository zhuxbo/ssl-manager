import { ref } from "vue";
import dayjs from "dayjs";
import { countryCodes } from "@/views/system/country";

export function useOrganizationTable() {
  const tableRef = ref();
  const selectedIds = ref([]);

  const handleSelectionChange = val => {
    selectedIds.value = val.map(v => v.id);
    // 每次选择后重置表格高度
    tableRef.value.setAdaptive();
  };

  const handleCancelSelection = () => {
    selectedIds.value = [];
    tableRef.value.getTableRef().clearSelection();
  };

  const tableColumns: TableColumnList = [
    {
      label: "勾选列",
      type: "selection",
      reserveSelection: true
    },
    {
      label: "名称",
      prop: "name",
      minWidth: 120
    },
    {
      label: "信用代码",
      prop: "registration_number",
      minWidth: 120
    },
    {
      label: "国家",
      prop: "country",
      minWidth: 100,
      formatter: ({ country }) => {
        return countryCodes.find(item => item.value === country)?.label;
      }
    },
    {
      label: "电话",
      prop: "phone",
      minWidth: 120
    },
    {
      label: "创建时间",
      prop: "created_at",
      minWidth: 180,
      formatter: ({ created_at }) => {
        return created_at
          ? dayjs(created_at).format("YYYY-MM-DD HH:mm:ss")
          : "-";
      }
    },
    {
      label: "操作",
      fixed: "right",
      width: 180,
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
    handleCancelSelection,
    handleRowClick
  };
}
