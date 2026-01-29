import "plus-pro-components/es/components/search/style/css";
import type { PlusColumn } from "plus-pro-components";
import { debounce } from "lodash-es";
import ReRemoteSelect from "@shared/components/ReRemoteSelect";

export const useDelegationSearch = (onSearch: () => void) => {
  const debouncedSearch = debounce(() => {
    onSearch();
  }, 500);

  const searchColumns: PlusColumn[] = [
    {
      label: "快速搜索",
      prop: "quickSearch",
      valueType: "input",
      fieldProps: {
        placeholder: "用户名/邮箱/域名/标签"
      },
      onChange: () => {
        debouncedSearch();
      }
    },
    {
      label: "用户",
      prop: "user_id",
      valueType: "select",
      renderField: (value, onChange) => {
        return (
          <ReRemoteSelect
            modelValue={value}
            uri="/user"
            searchField="quickSearch"
            labelField="username"
            valueField="id"
            itemsField="items"
            totalField="total"
            placeholder="请选择用户"
            onChange={val => {
              onChange(val);
              debouncedSearch();
            }}
          />
        );
      }
    },
    {
      label: "委托域",
      prop: "zone",
      valueType: "input",
      fieldProps: {
        placeholder: "请输入域名"
      },
      onChange: () => {
        debouncedSearch();
      }
    },
    {
      label: "委托前缀",
      prop: "prefix",
      valueType: "select",
      options: [
        { label: "_acme-challenge (ACME)", value: "_acme-challenge" },
        { label: "_dnsauth (DigiCert/TrustAsia)", value: "_dnsauth" },
        { label: "_pki-validation (Sectigo)", value: "_pki-validation" },
        { label: "_certum (Certum)", value: "_certum" }
      ],
      fieldProps: {
        placeholder: "请选择前缀"
      }
    },
    {
      label: "状态",
      prop: "valid",
      valueType: "select",
      options: [
        { label: "有效", value: true },
        { label: "无效", value: false }
      ],
      fieldProps: {
        placeholder: "请选择状态"
      }
    }
  ];

  return { searchColumns };
};
