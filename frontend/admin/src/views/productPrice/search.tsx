import "plus-pro-components/es/components/search/style/css";
import type { PlusColumn } from "plus-pro-components";
import { debounce } from "lodash-es";
import ReRemoteSelect from "@shared/components/ReRemoteSelect";
import { periodOptions } from "@/views/system/dictionary";

export const useProductPriceSearch = (onSearch: () => void) => {
  const debouncedSearch = debounce(() => {
    onSearch();
  }, 500);

  const searchColumns: PlusColumn[] = [
    {
      label: "产品",
      prop: "product_id",
      valueType: "select",
      fieldProps: {
        clearable: true
      },
      renderField: (value, onChange) => {
        return (
          <ReRemoteSelect
            modelValue={value}
            uri="/product"
            searchField="quickSearch"
            labelField="name"
            valueField="id"
            itemsField="items"
            totalField="total"
            placeholder="请选择产品"
            onChange={onChange}
          />
        );
      }
    },
    {
      label: "级别",
      prop: "level_code",
      valueType: "select",
      fieldProps: {
        clearable: true
      },
      renderField: (value, onChange) => {
        return (
          <ReRemoteSelect
            modelValue={value}
            uri="/user-level"
            searchField="quickSearch"
            labelField="name"
            valueField="code"
            itemsField="items"
            totalField="total"
            placeholder="请选择级别"
            onChange={onChange}
          />
        );
      }
    },
    {
      label: "周期",
      prop: "period",
      valueType: "select",
      fieldProps: {
        clearable: true,
        placeholder: "请选择周期"
      },
      options: periodOptions
    }
  ];

  return { searchColumns, debouncedSearch };
};
