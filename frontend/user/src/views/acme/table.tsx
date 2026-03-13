import dayjs from "dayjs";
import { status, statusType } from "./dictionary";

export function useAcmeTable() {
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
      label: "品牌",
      prop: "brand",
      minWidth: 100
    },
    {
      label: "标准域名额度",
      prop: "purchased_standard_count",
      minWidth: 100
    },
    {
      label: "通配符额度",
      prop: "purchased_wildcard_count",
      minWidth: 100
    },
    {
      label: "金额",
      prop: "amount",
      minWidth: 80
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
      width: 180,
      slot: "operation"
    }
  ];

  return {
    tableColumns
  };
}
