import "plus-pro-components/es/components/search/style/css";
import { debounce } from "lodash-es";
import { getPickerShortcuts } from "@shared/utils";
import type { PlusColumn } from "plus-pro-components";
import { countryCodes } from "@/views/system/country";

export function useOrganizationSearch(onSearch) {
  // 防抖处理搜索
  const debouncedSearch = debounce(() => {
    onSearch();
  }, 500);

  const searchColumns: PlusColumn[] = [
    {
      label: "快速搜索",
      prop: "quickSearch",
      valueType: "input",
      fieldProps: {
        placeholder: "名称/信用代码/电话"
      },
      onChange: () => {
        debouncedSearch();
      }
    },
    {
      label: "名称",
      prop: "name",
      valueType: "input",
      fieldProps: {
        placeholder: "请输入组织名称"
      }
    },
    {
      label: "信用代码",
      prop: "registration_number",
      valueType: "input",
      fieldProps: {
        placeholder: "请输入信用代码"
      }
    },
    {
      label: "国家",
      prop: "country",
      valueType: "select",
      fieldProps: {
        placeholder: "请选择国家"
      },
      options: countryCodes.map(item => ({
        label: item.label,
        value: item.value
      }))
    },
    {
      label: "电话",
      prop: "phone",
      valueType: "input",
      fieldProps: {
        placeholder: "请输入电话",
        type: "number"
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

  return {
    searchColumns
  };
}
