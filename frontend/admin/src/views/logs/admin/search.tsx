import "plus-pro-components/es/components/search/style/css";
import type { PlusColumn } from "plus-pro-components";
import { getPickerShortcuts } from "@shared/utils";
import { ReRemoteSelect } from "@shared/components/ReRemoteSelect";

export const searchColumns: PlusColumn[] = [
  {
    label: "管理员",
    prop: "admin_id",
    valueType: "select",
    renderField: (value, onChange) => {
      return (
        <ReRemoteSelect
          modelValue={value}
          uri="/admin"
          searchField="quickSearch"
          labelField="username"
          valueField="id"
          itemsField="items"
          totalField="total"
          placeholder="请选择管理员"
          onChange={onChange}
        />
      );
    }
  },
  {
    label: "状态",
    prop: "status",
    valueType: "select",
    options: [
      {
        label: "成功",
        value: "1"
      },
      {
        label: "失败",
        value: "0"
      }
    ],
    fieldProps: {
      placeholder: "请选择状态"
    }
  },
  {
    label: "状态码",
    prop: "status_code",
    valueType: "input",
    fieldProps: {
      placeholder: "请输入状态码"
    }
  },
  {
    label: "参数",
    prop: "params",
    valueType: "input",
    fieldProps: {
      placeholder: "请输入参数"
    }
  },
  {
    label: "响应",
    prop: "response",
    valueType: "input",
    fieldProps: {
      placeholder: "请输入响应"
    }
  },
  {
    label: "请求URL",
    prop: "url",
    valueType: "input",
    fieldProps: {
      placeholder: "请输入请求URL"
    }
  },
  {
    label: "请求方法",
    prop: "method",
    valueType: "select",
    fieldProps: {
      placeholder: "请选择请求方法"
    },
    options: [
      {
        label: "GET",
        value: "GET"
      },
      {
        label: "POST",
        value: "POST"
      },
      {
        label: "PUT",
        value: "PUT"
      },
      {
        label: "DELETE",
        value: "DELETE"
      },
      {
        label: "PATCH",
        value: "PATCH"
      },
      {
        label: "OPTIONS",
        value: "OPTIONS"
      }
    ]
  },
  {
    label: "所属模块",
    prop: "module",
    valueType: "input",
    fieldProps: {
      placeholder: "请输入所属模块"
    }
  },
  {
    label: "动作",
    prop: "action",
    valueType: "input",
    fieldProps: {
      placeholder: "请输入动作"
    }
  },
  {
    label: "IP地址",
    prop: "ip",
    valueType: "input",
    fieldProps: {
      placeholder: "请输入IP地址"
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
