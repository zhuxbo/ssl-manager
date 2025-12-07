import { ref } from "vue";
import { periodLabels, productTypeLabels } from "../system/dictionary";

export const useProductTable = () => {
  const tableRef = ref();

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
      width: 100,
      formatter(row) {
        return row.price?.price && row.price.price !== "0.00"
          ? parseFloat(row.price.price) + "/" + periodLabels[row.price.period]
          : "-";
      }
    },
    {
      label: "附加标准域名价格",
      prop: "price.alternative_standard_price",
      width: 150,
      formatter(row) {
        return row.price?.alternative_standard_price &&
          row.price.alternative_standard_price !== "0.00"
          ? parseFloat(row.price.alternative_standard_price) +
              "/" +
              periodLabels[row.price.period]
          : "-";
      }
    },
    {
      label: "附加通配符价格",
      prop: "price.alternative_wildcard_price",
      width: 150,
      formatter(row) {
        return row.price?.alternative_wildcard_price &&
          row.price.alternative_wildcard_price !== "0.00"
          ? parseFloat(row.price.alternative_wildcard_price) +
              "/" +
              periodLabels[row.price.period]
          : "-";
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
    tableColumns
  };
};
