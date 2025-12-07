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

export function useFundsTable() {
  const tableRef = ref();

  const tableColumns: TableColumnList = [
    {
      label: "ID",
      prop: "id",
      width: 130
    },
    {
      label: "金额",
      prop: "amount",
      width: 100
    },
    {
      label: "类型",
      prop: "type",
      width: 80,
      cellRenderer: ({ row, props }) => (
        <el-tag size={props.size} type={fundTypeMap[row.type]} effect="plain">
          {fundTypeOptions.find(item => item.value === row.type)?.label}
        </el-tag>
      )
    },
    {
      label: "支付方式",
      prop: "pay_method",
      width: 100,
      cellRenderer: ({ row, props }) => (
        <el-tag
          size={props.size}
          type={fundPayMethodMap[row.pay_method]}
          effect="plain"
        >
          {
            fundPayMethodOptions.find(item => item.value === row.pay_method)
              ?.label
          }
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
      width: 80,
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
      width: 170,
      formatter: ({ created_at }) => {
        return created_at
          ? dayjs(created_at).format("YYYY-MM-DD HH:mm:ss")
          : "-";
      }
    }
  ];

  return {
    tableRef,
    tableColumns
  };
}
