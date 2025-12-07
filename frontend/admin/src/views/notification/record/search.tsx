import "plus-pro-components/es/components/search/style/css";
import { debounce } from "lodash-es";
import type { PlusColumn } from "plus-pro-components";
import { ReRemoteSelect } from "@shared/components/ReRemoteSelect";
import { getPickerShortcuts } from "@shared/utils";

const statusOptions = [
  { label: "全部", value: "" },
  { label: "待发送", value: "pending" },
  { label: "发送中", value: "sending" },
  { label: "已发送", value: "sent" },
  { label: "失败", value: "failed" }
];

export function useNotificationRecordSearch(onSearch: () => void) {
  const debouncedSearch = debounce(() => {
    onSearch();
  }, 500);

  const searchColumns: PlusColumn[] = [
    {
      label: "用户",
      prop: "user_id",
      valueType: "select",
      renderField: (value, onChange) => (
        <ReRemoteSelect
          modelValue={value}
          uri="/user"
          searchField="quickSearch"
          labelField="username"
          valueField="id"
          itemsField="items"
          totalField="total"
          placeholder="搜索用户名/邮箱"
          clearable
          onChange={(val: number | null) => {
            onChange(val);
            debouncedSearch();
          }}
        />
      )
    },
    {
      label: "模板标识",
      prop: "template_code",
      valueType: "input",
      fieldProps: {
        placeholder: "cert_issued",
        clearable: true
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
      label: "创建时间",
      prop: "created_at",
      valueType: "date-picker",
      fieldProps: {
        type: "daterange",
        shortcuts: getPickerShortcuts(),
        rangeSeparator: "至",
        startPlaceholder: "开始日期",
        endPlaceholder: "结束日期",
        valueFormat: "YYYY-MM-DD"
      }
    }
  ];

  return {
    searchColumns
  };
}
