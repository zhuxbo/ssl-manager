import "plus-pro-components/es/components/search/style/css";
import { debounce } from "lodash-es";
import type { PlusColumn } from "plus-pro-components";
import { statusOptions } from "./dictionary";

export function useAcmeOrderSearch(onSearch) {
  const debouncedSearch = debounce(() => {
    onSearch();
  }, 500);

  const searchColumns: PlusColumn[] = [
    {
      label: "品牌",
      prop: "brand",
      valueType: "input",
      fieldProps: {
        placeholder: "请输入品牌"
      },
      onChange: () => {
        debouncedSearch();
      }
    },
    {
      label: "状态",
      prop: "status",
      valueType: "select",
      fieldProps: {
        placeholder: "请选择状态"
      },
      options: statusOptions,
      onChange: () => {
        debouncedSearch();
      }
    }
  ];

  return {
    searchColumns
  };
}
