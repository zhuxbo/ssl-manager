import "plus-pro-components/es/components/search/style/css";
import { debounce } from "lodash-es";
import { getPickerShortcuts } from "@shared/utils";
import type { PlusColumn } from "plus-pro-components";
import {
  fundPayMethodOptions,
  fundStatusOptions,
  fundTypeOptions
} from "./dictionary";

export function useFundsSearch(onSearch) {
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
        placeholder: "ID/用户名/备注"
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
      options: fundStatusOptions,
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
      label: "金额",
      prop: "amount",
      renderField: (value, onChange) => {
        const validateAndUpdate = (
          minVal: number | undefined,
          maxVal: number | undefined
        ) => {
          // 只有当最小值和最大值都存在时才进行比较
          if (minVal !== undefined && maxVal !== undefined && minVal > maxVal) {
            return;
          }
          onChange([minVal, maxVal]);
        };

        return (
          <div class="flex items-center gap-2 w-full">
            <el-input
              class="flex-1"
              modelValue={value?.[0]?.toString()}
              onUpdate:modelValue={val => {
                const num = val === "" ? undefined : Number(val);
                validateAndUpdate(num, value?.[1]);
              }}
              placeholder="最小金额"
              clearable
            />
            <span class="flex-none">至</span>
            <el-input
              class="flex-1"
              modelValue={value?.[1]?.toString()}
              onUpdate:modelValue={val => {
                const num = val === "" ? undefined : Number(val);
                validateAndUpdate(value?.[0], num);
              }}
              placeholder="最大金额"
              clearable
            />
          </div>
        );
      }
    },
    {
      label: "类型",
      prop: "type",
      valueType: "select",
      fieldProps: {
        placeholder: "请选择类型"
      },
      options: fundTypeOptions
    },
    {
      label: "支付方式",
      prop: "pay_method",
      valueType: "select",
      fieldProps: {
        placeholder: "请选择支付方式"
      },
      options: fundPayMethodOptions
    },
    {
      label: "支付编号",
      prop: "pay_sn",
      valueType: "input",
      fieldProps: {
        placeholder: "请输入支付编号"
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
