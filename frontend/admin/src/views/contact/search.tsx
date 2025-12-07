import "plus-pro-components/es/components/search/style/css";
import type { PlusColumn } from "plus-pro-components";
import { getPickerShortcuts } from "@shared/utils";
import { debounce } from "lodash-es";

export const useContactSearch = (onSearch: () => void) => {
  const debouncedSearch = debounce(() => {
    onSearch();
  }, 500);

  const searchColumns: PlusColumn[] = [
    {
      label: "快速搜索",
      prop: "quickSearch",
      valueType: "input",
      fieldProps: {
        placeholder: "用户名/姓名/邮箱/手机号"
      },
      onChange: () => {
        debouncedSearch();
      }
    },
    {
      label: "用户名",
      prop: "username",
      valueType: "input",
      fieldProps: {
        placeholder: "请输入用户名"
      }
    },
    {
      label: "名字",
      prop: "first_name",
      valueType: "input",
      fieldProps: {
        placeholder: "请输入名字"
      }
    },
    {
      label: "姓氏",
      prop: "last_name",
      valueType: "input",
      fieldProps: {
        placeholder: "请输入姓氏"
      }
    },
    {
      label: "邮箱",
      prop: "email",
      valueType: "input",
      fieldProps: {
        placeholder: "请输入邮箱"
      }
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

  return { searchColumns };
};
