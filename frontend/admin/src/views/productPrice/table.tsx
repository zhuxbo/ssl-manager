import { ref } from "vue";
import dayjs from "dayjs";
import { periodOptions } from "@/views/system/dictionary";

export const useProductPriceTable = () => {
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
      label: "产品",
      prop: "product.name",
      minWidth: 100
    },
    {
      label: "级别",
      prop: "level.name",
      minWidth: 100
    },
    {
      label: "周期",
      prop: "period",
      minWidth: 80,
      formatter: ({ period }) =>
        periodOptions.find(item => item.value === period)?.label
    },
    {
      label: "价格",
      prop: "price",
      minWidth: 100,
      formatter: ({ price }) => `¥${price}`
    },
    {
      label: "附加标准域名价格",
      prop: "alternative_standard_price",
      minWidth: 120,
      formatter: ({ alternative_standard_price }) =>
        alternative_standard_price ? `¥${alternative_standard_price}` : "-"
    },
    {
      label: "附加通配符价格",
      prop: "alternative_wildcard_price",
      minWidth: 120,
      formatter: ({ alternative_wildcard_price }) =>
        alternative_wildcard_price ? `¥${alternative_wildcard_price}` : "-"
    },
    {
      label: "创建时间",
      prop: "created_at",
      width: 180,
      formatter: ({ created_at }) =>
        created_at ? dayjs(created_at).format("YYYY-MM-DD HH:mm:ss") : "-"
    }
  ];

  const handleRowClick = (row: any) => {
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
