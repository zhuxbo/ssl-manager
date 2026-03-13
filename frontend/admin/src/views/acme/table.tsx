import dayjs from "dayjs";
import { status, statusType } from "./dictionary";
import { createUsernameRenderer } from "@/views/system/username";

export function useAcmeTable() {
  const tableColumns: TableColumnList = [
    {
      label: "ID",
      prop: "id",
      minWidth: 100
    },
    {
      label: "用户",
      prop: "user.username",
      minWidth: 120,
      cellRenderer: createUsernameRenderer("user.username")
    },
    {
      label: "品牌",
      prop: "brand",
      minWidth: 100
    },
    {
      label: "产品",
      minWidth: 150,
      cellRenderer: ({ row }) => row.product?.name || "-"
    },
    {
      label: "状态",
      prop: "status",
      minWidth: 80,
      cellRenderer: ({ row }) => {
        const st = row.status;
        return st ? (
          <el-tag type={statusType[st] || "info"}>{status[st] || st}</el-tag>
        ) : (
          "-"
        );
      }
    },
    {
      label: "标准域名额度",
      prop: "purchased_standard_count",
      minWidth: 120
    },
    {
      label: "通配符域名额度",
      prop: "purchased_wildcard_count",
      minWidth: 130
    },
    {
      label: "金额",
      prop: "amount",
      minWidth: 80
    },
    {
      label: "EAB Kid",
      prop: "eab_kid",
      minWidth: 150,
      cellRenderer: ({ row }) => row.eab_kid || "-"
    },
    {
      label: "有效期",
      minWidth: 170,
      cellRenderer: ({ row }) => {
        const from = row.period_from
          ? dayjs(row.period_from).format("YYYY-MM-DD")
          : "-";
        const till = row.period_till
          ? dayjs(row.period_till).format("YYYY-MM-DD")
          : "-";
        return `${from} ~ ${till}`;
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
      width: 200,
      slot: "operation"
    }
  ];

  return {
    tableColumns
  };
}
