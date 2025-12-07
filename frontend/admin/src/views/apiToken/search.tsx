import "plus-pro-components/es/components/search/style/css";
import type { PlusColumn } from "plus-pro-components";
import { debounce } from "lodash-es";

export const useApiTokenSearch = (onSearch: () => void) => {
  const debouncedSearch = debounce(() => {
    onSearch();
  }, 500);

  const searchColumns: PlusColumn[] = [
    {
      label: "用户名",
      prop: "username",
      valueType: "input",
      fieldProps: {
        placeholder: "请输入用户名"
      }
    },
    {
      label: "状态",
      prop: "status",
      valueType: "select",
      options: [
        {
          label: "启用",
          value: 1
        },
        {
          label: "禁用",
          value: 0
        }
      ],
      fieldProps: {
        placeholder: "请选择状态"
      }
    }
  ];

  return { searchColumns, debouncedSearch };
};
