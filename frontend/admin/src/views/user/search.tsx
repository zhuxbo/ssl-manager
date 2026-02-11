import "plus-pro-components/es/components/search/style/css";
import type { PlusColumn } from "plus-pro-components";
import { getPickerShortcuts } from "@shared/utils";
import { debounce } from "lodash-es";
import ReRemoteSelect from "@shared/components/ReRemoteSelect";

export const useUserSearch = (onSearch: () => void) => {
  const debouncedSearch = debounce(() => {
    onSearch();
  }, 500);

  const searchColumns: PlusColumn[] = [
    {
      label: "快速搜索",
      prop: "quickSearch",
      valueType: "input",
      fieldProps: {
        placeholder: "用户名/邮箱/手机号"
      },
      onChange: () => {
        debouncedSearch();
      }
    },
    {
      label: "状态",
      prop: "status",
      valueType: "select",
      options: [
        {
          label: "正常",
          value: "1"
        },
        {
          label: "禁用",
          value: "0"
        }
      ],
      fieldProps: {
        placeholder: "请选择状态"
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
      label: "邮箱",
      prop: "email",
      valueType: "input",
      fieldProps: {
        placeholder: "请输入邮箱"
      }
    },
    {
      label: "手机号",
      prop: "mobile",
      valueType: "input",
      fieldProps: {
        placeholder: "请输入手机号",
        type: "number"
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
            queryParams={{ custom: 0 }}
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
      label: "定制级别",
      prop: "custom_level_code",
      valueType: "select",
      fieldProps: {
        clearable: true
      },
      renderField: (value, onChange) => {
        return (
          <ReRemoteSelect
            modelValue={value}
            uri="/user-level"
            queryParams={{ custom: 1 }}
            searchField="quickSearch"
            labelField="name"
            valueField="code"
            itemsField="items"
            totalField="total"
            placeholder="请选择定制级别"
            onChange={onChange}
          />
        );
      }
    },
    {
      label: "余额",
      prop: "balance",
      hasLabel: true,
      renderField: (value, onChange) => {
        const current = (value as [string | null, string | null]) || [
          null,
          null
        ];
        return (
          <div class="flex items-center gap-1" style="width: 100%">
            <el-input
              modelValue={current[0] ?? ""}
              placeholder="最小值"
              type="number"
              step="0.01"
              style="flex: 1"
              onUpdate:modelValue={(v: string) => {
                onChange([v || null, current[1]]);
              }}
            />
            <span class="text-gray-400 shrink-0">~</span>
            <el-input
              modelValue={current[1] ?? ""}
              placeholder="最大值"
              type="number"
              step="0.01"
              style="flex: 1"
              onUpdate:modelValue={(v: string) => {
                onChange([current[0], v || null]);
              }}
            />
          </div>
        );
      }
    },
    {
      label: "信用额度",
      prop: "credit_limit",
      hasLabel: true,
      renderField: (value, onChange) => {
        return (
          <el-input
            modelValue={value ?? ""}
            placeholder="最大额度"
            type="number"
            step="0.01"
            min={0}
            onUpdate:modelValue={(v: string) => onChange(v || undefined)}
          >
            {{ prefix: () => <span class="text-gray-500">-</span> }}
          </el-input>
        );
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
