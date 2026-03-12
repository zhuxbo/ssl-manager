import "plus-pro-components/es/components/search/style/css";
import type { PlusColumn } from "plus-pro-components";
import { statusOptions } from "../acme-order/dictionary";

export const searchColumns: PlusColumn[] = [
  {
    label: "域名",
    prop: "domain",
    valueType: "input",
    fieldProps: {
      placeholder: "请输入域名"
    }
  },
  {
    label: "状态",
    prop: "status",
    valueType: "select",
    options: statusOptions,
    fieldProps: {
      placeholder: "请选择状态"
    }
  },
  {
    label: "订单ID",
    prop: "order_id",
    valueType: "input",
    fieldProps: {
      placeholder: "请输入订单ID"
    }
  }
];
