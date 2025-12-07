import dayjs from "dayjs";
import { useRouter } from "vue-router";
import {
  status,
  statusType,
  action,
  actionType,
  channel,
  channelType
} from "@/views/order/dictionary";

export function useCertTable() {
  const router = useRouter();

  const tableColumns: TableColumnList = [
    {
      label: "ID",
      prop: "id",
      width: 140
    },
    {
      label: "订单ID",
      prop: "order_id",
      width: 140,
      cellRenderer: ({ row }) => {
        const handleClick = () => {
          router.push({
            path: "/order",
            query: {
              id: row.order_id
            }
          });
        };
        return (
          <span class="cursor-pointer" onClick={handleClick}>
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
      label: "金额",
      prop: "amount",
      width: 100
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
      label: "渠道",
      prop: "channel",
      width: 100,
      cellRenderer: ({ row }) => {
        return (
          <el-tag type={channelType[row.channel]}>
            {channel[row.channel]}
          </el-tag>
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
        return issued_at ? dayjs(issued_at).format("YYYY-MM-DD HH:mm:ss") : "-";
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
    },
    {
      label: "操作",
      fixed: "right",
      width: 80,
      slot: "operation"
    }
  ];

  return tableColumns;
}
