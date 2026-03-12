import dayjs from "dayjs";
import {
  status,
  statusType,
  action,
  actionType,
  validationMethod
} from "./dictionary";

export function useAcmeOrderTable() {
  const tableColumns: TableColumnList = [
    {
      label: "ID",
      prop: "id",
      minWidth: 100
    },
    {
      label: "产品",
      minWidth: 150,
      cellRenderer: ({ row }) => row.product?.name || "-"
    },
    {
      label: "域名",
      prop: "latest_cert.common_name",
      minWidth: 150,
      cellRenderer: ({ row }) => row.latest_cert?.common_name || "-"
    },
    {
      label: "验证方式",
      minWidth: 100,
      cellRenderer: ({ row }) => {
        const method = row.latest_cert?.validation_method;
        return method ? validationMethod[method] || method : "-";
      }
    },
    {
      label: "操作",
      prop: "latest_cert.action",
      minWidth: 80,
      cellRenderer: ({ row }) => {
        const act = row.latest_cert?.action;
        return act ? (
          <el-tag type={actionType[act]}>{action[act]}</el-tag>
        ) : (
          "-"
        );
      }
    },
    {
      label: "状态",
      prop: "latest_cert.status",
      minWidth: 80,
      cellRenderer: ({ row }) => {
        const st = row.latest_cert?.status;
        return st ? (
          <el-tag type={statusType[st] || "info"}>{status[st]}</el-tag>
        ) : (
          "-"
        );
      }
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
    },
    {
      label: "操作",
      fixed: "right",
      width: 120,
      slot: "operation"
    }
  ];

  return {
    tableColumns
  };
}
