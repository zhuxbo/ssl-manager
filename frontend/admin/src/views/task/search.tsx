import "plus-pro-components/es/components/search/style/css";
import { debounce } from "lodash-es";
import type { PlusColumn } from "plus-pro-components";
import { getPickerShortcuts } from "@shared/utils";
import { actionOptions, statusOptions } from "./dictionary";

export function useTaskSearch(onSearch) {
  // 防抖处理搜索
  const debouncedSearch = debounce(() => {
    onSearch();
  }, 500);

  const searchColumns: PlusColumn[] = [
    {
      label: "动作",
      prop: "action",
      valueType: "select",
      options: actionOptions,
      fieldProps: {
        placeholder: "请选择动作"
      },
      onChange: () => {
        debouncedSearch();
      }
    },
    {
      label: "状态",
      prop: "status",
      valueType: "select",
      options: statusOptions,
      fieldProps: {
        placeholder: "请选择状态"
      },
      onChange: () => {
        debouncedSearch();
      }
    },
    {
      label: "来源",
      prop: "source",
      valueType: "input",
      fieldProps: {
        placeholder: "请输入来源"
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

  return searchColumns;
}
