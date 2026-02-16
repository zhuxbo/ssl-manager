import { reactive, ref } from "vue";
import { periodLabels, productTypeLabels } from "../system/dictionary";

const formatPrice = (val: string | undefined) => {
  if (!val || val === "0.00") return "";
  return parseFloat(val);
};

const renderPriceCell = (
  row: any,
  expandedRows: Set<number>,
  field: string
) => {
  if (expandedRows.has(row.id) && row.prices) {
    const lines = Object.entries(row.prices)
      .map(([period, price]: [string, any]) => {
        const val = formatPrice(price[field]);
        if (!val) return null;
        return `${val}/${periodLabels[period] || period + "月"}`;
      })
      .filter(Boolean);
    return lines.length
      ? <>{lines.map((line, i) => <div key={i}>{line}</div>)}</>
      : "-";
  }
  const val = formatPrice(row.price?.[field]);
  return val ? `${val}/${periodLabels[row.price?.period] || ""}` : "-";
};

export const useProductTable = () => {
  const tableRef = ref();
  const expandedRows = reactive(new Set<number>());

  const tableColumns: TableColumnList = [
    {
      label: "名称",
      prop: "name",
      minWidth: 150
    },
    {
      label: "产品类型",
      prop: "product_type",
      width: 100,
      formatter(row) {
        return (
          productTypeLabels[row.product_type] || row.product_type || "SSL证书"
        );
      }
    },
    {
      label: "说明",
      prop: "remark",
      minWidth: 150
    },
    {
      label: "价格",
      prop: "price.price",
      width: 120,
      cellRenderer({ row }) {
        return renderPriceCell(row, expandedRows, "price");
      }
    },
    {
      label: "附加标准域名价格",
      prop: "price.alternative_standard_price",
      width: 150,
      cellRenderer({ row }) {
        return renderPriceCell(row, expandedRows, "alternative_standard_price");
      }
    },
    {
      label: "附加通配符价格",
      prop: "price.alternative_wildcard_price",
      width: 150,
      cellRenderer({ row }) {
        return renderPriceCell(row, expandedRows, "alternative_wildcard_price");
      }
    },
    {
      label: "操作",
      slot: "operation",
      width: 110,
      fixed: "right"
    }
  ];

  return {
    tableRef,
    tableColumns,
    expandedRows
  };
};
