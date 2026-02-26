import "plus-pro-components/es/components/search/style/css";
import type { PlusColumn } from "plus-pro-components";
import { debounce } from "lodash-es";
import { getPickerShortcuts } from "../../shared/utils";
import { platformOptions, rechargedOptions } from "./dictionary";

export const useAgisoSearch = (onSearch: () => void) => {
  const debouncedSearch = debounce(() => {
    onSearch();
  }, 500);

  const searchColumns: PlusColumn[] = [
    {
      label: "快速搜索",
      prop: "quickSearch",
      valueType: "input",
      fieldProps: { placeholder: "平台/交易单号/用户名" },
      onChange: () => debouncedSearch()
    },
    {
      label: "充值状态",
      prop: "recharged",
      valueType: "select",
      options: rechargedOptions,
      fieldProps: { placeholder: "请选择充值状态" },
      onChange: () => debouncedSearch()
    },
    {
      label: "用户名",
      prop: "username",
      valueType: "input",
      fieldProps: { placeholder: "请输入用户名" }
    },
    {
      label: "平台",
      prop: "platform",
      valueType: "select",
      options: platformOptions,
      fieldProps: { placeholder: "请选择平台" }
    },
    {
      label: "类型",
      prop: "type",
      valueType: "input",
      fieldProps: { placeholder: "请输入类型" }
    },
    {
      label: "产品代码",
      prop: "product_code",
      valueType: "input",
      fieldProps: { placeholder: "请输入产品代码" }
    },
    {
      label: "周期",
      prop: "period",
      valueType: "input",
      fieldProps: { placeholder: "请输入周期" }
    },
    {
      label: "订单ID",
      prop: "order_id",
      valueType: "input",
      fieldProps: { placeholder: "请输入订单ID" }
    },
    {
      label: "交易单号",
      prop: "tid",
      valueType: "input",
      fieldProps: { placeholder: "请输入交易单号" }
    },
    {
      label: "创建时间",
      prop: "created_at",
      valueType: "date-picker",
      fieldProps: {
        type: "daterange",
        rangeSeparator: "至",
        startPlaceholder: "开始日期",
        endPlaceholder: "结束日期",
        valueFormat: "YYYY-MM-DD",
        shortcuts: getPickerShortcuts()
      }
    }
  ];

  return { searchColumns, debouncedSearch };
};
