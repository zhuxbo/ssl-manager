import "plus-pro-components/es/components/search/style/css";
import type { PlusColumn } from "plus-pro-components";
import { statusOptions } from "@/views/order/dictionary";

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
  },
  {
    label: "签发时间",
    prop: "issued_at",
    valueType: "date-picker",
    fieldProps: {
      type: "daterange",
      startPlaceholder: "开始时间",
      endPlaceholder: "结束时间"
    }
  },
  {
    label: "过期时间",
    prop: "expires_at",
    valueType: "date-picker",
    fieldProps: {
      type: "daterange",
      startPlaceholder: "开始时间",
      endPlaceholder: "结束时间"
    }
  }
];
