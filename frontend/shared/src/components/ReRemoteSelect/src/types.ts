export interface RemoteSelectOption {
  label: string;
  value: any;
}

export interface RemoteSelectProps {
  modelValue: any;
  uri: string; // 远程搜索API地址
  searchField?: string; // 搜索字段名
  labelField?: string; // 显示的标签字段名
  valueField?: string; // 值字段名
  placeholder?: string; // 占位符
  pageSize?: number; // 每页数量
  showPagination?: boolean; // 是否显示分页
  queryParams?: Record<string, any>; // 额外的查询参数
  itemsField?: string; // 返回数据中的列表字段名
  totalField?: string; // 返回数据中的总数字段名
  refreshKey?: string | number; // 用于控制组件重置的唯一标识
  multiple?: boolean; // 是否支持多选
}

export interface RemoteSelectEmits {
  (e: "update:modelValue" | "change", value: any): void;
}
