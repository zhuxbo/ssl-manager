import dayjs from "dayjs";
import { useRouter } from "vue-router";
import { status, statusType, action, actionType } from "../acme-order/dictionary";

export function useAcmeCertTable() {
  const router = useRouter();

  const tableColumns: TableColumnList = [
    {
      label: "ID",
      prop: "id",
      width: 100
    },
    {
      label: "订单ID",
      prop: "order_id",
      width: 100,
      cellRenderer: ({ row }) => {
        const handleClick = () => {
          router.push({
            name: "AcmeOrderDetails",
            params: { ids: row.order_id }
          });
        };
        return (
          <span class="cursor-pointer text-primary" onClick={handleClick}>
            {row.order_id}
          </span>
        );
      }
    },
    {
      label: "主域名",
      prop: "common_name",
      minWidth: 150
    },
    {
      label: "操作",
      prop: "action",
      width: 100,
      cellRenderer: ({ row }) => {
        return (
          <el-tag type={actionType[row.action]}>{action[row.action]}</el-tag>
        );
      }
    },
    {
      label: "状态",
      prop: "status",
      width: 100,
      cellRenderer: ({ row }) => {
        return (
          <el-tag type={statusType[row.status] || "info"}>
            {status[row.status]}
          </el-tag>
        );
      }
    },
    {
      label: "签发时间",
      prop: "issued_at",
      width: 180,
      formatter: ({ issued_at }) => {
        return issued_at
          ? dayjs(issued_at).format("YYYY-MM-DD HH:mm:ss")
          : "-";
      }
    },
    {
      label: "过期时间",
      prop: "expires_at",
      width: 180,
      formatter: ({ expires_at }) => {
        return expires_at
          ? dayjs(expires_at).format("YYYY-MM-DD HH:mm:ss")
          : "-";
      }
    }
  ];

  return tableColumns;
}
