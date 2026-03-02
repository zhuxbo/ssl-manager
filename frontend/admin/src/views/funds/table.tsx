import { ref } from "vue";
import dayjs from "dayjs";
import {
  fundPayMethodOptions,
  fundStatusOptions,
  fundTypeOptions,
  fundPayMethodMap,
  fundTypeMap,
  fundStatusMap
} from "./dictionary";
import { createUsernameRenderer } from "@/views/system/username";

export function useFundsTable() {
  const tableRef = ref();
  const selectedIds = ref([]);

  const handleSelectionChange = (val: any) => {
    selectedIds.value = val.map((row: any) => row.id);
    // 重置表格高度
    tableRef.value.setAdaptive();
  };

  const handleSelectionCancel = () => {
    tableRef.value?.getTableRef().clearSelection();
  };

  const tableColumns: TableColumnList = [
    {
      label: "勾选列",
      type: "selection",
      reserveSelection: true
    },
    {
      label: "ID",
      prop: "id",
      width: 130
    },
    {
      label: "用户名",
      prop: "user.username",
      width: 100,
      cellRenderer: createUsernameRenderer("user.username")
    },
    {
      label: "金额",
      prop: "amount",
      width: 100
    },
    {
      label: "类型",
      prop: "type",
      minWidth: 150,
      cellRenderer: ({ row, props }) => (
        <el-tag size={props.size} type={fundTypeMap[row.type]} effect="plain">
          {fundTypeOptions.find(item => item.value === row.type)?.label}
        </el-tag>
      )
    },
    {
      label: "支付方式",
      prop: "pay_method",
      minWidth: 150,
      cellRenderer: ({ row, props }) => (
        <el-tag
          size={props.size}
          type={fundPayMethodMap[row.pay_method]}
          effect="plain"
        >
          {fundPayMethodOptions.find(item => item.value === row.pay_method)
            ?.label ?? row.pay_method}
        </el-tag>
      )
    },
    {
      label: "支付编号",
      prop: "pay_sn",
      minWidth: 150
    },
    {
      label: "备注",
      prop: "remark",
      minWidth: 150
    },
    {
      label: "状态",
      prop: "status",
      width: 100,
      cellRenderer: ({ row, props }) => (
        <el-tag
          size={props.size}
          type={fundStatusMap[row.status]}
          effect="plain"
        >
          {fundStatusOptions.find(item => item.value === row.status)?.label}
        </el-tag>
      )
    },
    {
      label: "创建时间",
      prop: "created_at",
      minWidth: 180,
      formatter: ({ created_at }) =>
        created_at ? dayjs(created_at).format("YYYY-MM-DD HH:mm:ss") : "-"
    },
    {
      label: "操作",
      fixed: "right",
      slot: "operation",
      width: 110
    }
  ];

  const handleRowClick = (row: any, _column: any, event: any) => {
    // 通过事件目标判断是否点击了按钮
    const target = event.target as HTMLElement;
    if (
      target.tagName === "BUTTON" ||
      target.closest("button") ||
      target.closest(".el-button")
    ) {
      return;
    }
    // 切换当前行的选中状态
    tableRef.value?.getTableRef().toggleRowSelection(row);
  };

  return {
    tableRef,
    selectedIds,
    handleSelectionChange,
    handleSelectionCancel,
    tableColumns,
    handleRowClick
  };
}
