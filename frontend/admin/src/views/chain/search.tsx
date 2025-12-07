import "plus-pro-components/es/components/search/style/css";
import type { PlusColumn } from "plus-pro-components";
import { debounce } from "lodash-es";

export const useChainSearch = (onSearch: () => void) => {
  const debouncedSearch = debounce(() => {
    onSearch();
  }, 500);

  const searchColumns: PlusColumn[] = [
    {
      label: "名称",
      prop: "common_name",
      valueType: "input",
      fieldProps: {
        placeholder: "请输入名称"
      },
      onChange: () => {
        debouncedSearch();
      }
    }
  ];

  return { searchColumns };
};
