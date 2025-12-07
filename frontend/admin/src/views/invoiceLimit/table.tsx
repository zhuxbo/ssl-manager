import { ref } from "vue";
import { useRouter } from "vue-router";
import dayjs from "dayjs";
import { invoiceLimitTypeOptions, invoiceLimitTypeMap } from "./dictionary";
import { createUsernameRenderer } from "@/views/system/username";

export function useInvoiceLimitTable() {
  const tableRef = ref();
  const router = useRouter();

  const tableColumns: TableColumnList = [
    {
      label: "用户名",
      prop: "user.username",
      minWidth: 100,
      cellRenderer: createUsernameRenderer("user.username")
    },
    {
      label: "限额ID",
      prop: "limit_id",
      minWidth: 100,
      cellRenderer: ({ row }) => {
        const handleClick = () => {
          router.push({
            path: ["addfunds", "refunds"].includes(row.type)
              ? "/funds"
              : "/invoice",
            query: {
              id: row.limit_id
            }
          });
        };
        return (
          <span class="cursor-pointer" onClick={handleClick}>
            {row.limit_id}
          </span>
        );
      }
    },
    {
      label: "类型",
      prop: "type",
      width: 100,
      cellRenderer: ({ row, props }) => (
        <el-tag
          size={props.size}
          type={invoiceLimitTypeMap[row.type]}
          effect="plain"
        >
          {invoiceLimitTypeOptions.find(item => item.value === row.type)?.label}
        </el-tag>
      )
    },
    {
      label: "金额",
      prop: "amount",
      width: 100
    },
    {
      label: "操作前",
      prop: "limit_before",
      width: 100
    },
    {
      label: "操作后",
      prop: "limit_after",
      width: 100
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
