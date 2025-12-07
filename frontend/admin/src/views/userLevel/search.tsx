import "plus-pro-components/es/components/search/style/css";
import type { PlusColumn } from "plus-pro-components";
import { debounce } from "lodash-es";

export const useUserLevelSearch = (onSearch: () => void) => {
  const debouncedSearch = debounce(() => {
    onSearch();
  }, 500);

  const searchColumns: PlusColumn[] = [
    {
      label: "快速搜索",
      prop: "quickSearch",
      valueType: "input",
      fieldProps: {
        placeholder: "编号/名称"
      }
    },
    {
      label: "定制",
      prop: "custom",
      valueType: "select",
      options: [
        {
          label: "是",
          value: 1
        },
        {
          label: "否",
          value: 0
        }
      ],
      fieldProps: {
        placeholder: "请选择是否定制"
      }
    }
  ];

  return { searchColumns, debouncedSearch };
};
