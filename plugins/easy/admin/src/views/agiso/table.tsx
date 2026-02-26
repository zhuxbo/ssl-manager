import { ref } from "vue";
import { useRouter } from "vue-router";
import dayjs from "dayjs";
import {
  platformOptions,
  platformMap,
  rechargedOptions,
  rechargedMap
} from "./dictionary";
import { createUsernameRenderer } from "../../shared/utils";

export const useAgisoTable = () => {
  const tableRef = ref();
  const selectedIds = ref<number[]>([]);
  const router = useRouter();

  const handleSelectionChange = (val: any) => {
    selectedIds.value = val.map((row: any) => row.id);
    tableRef.value.setAdaptive();
  };

  const handleSelectionCancel = () => {
    tableRef.value?.getTableRef().clearSelection();
  };

  const tableColumns: any[] = [
    { label: "勾选列", type: "selection", reserveSelection: true },
    { label: "ID", prop: "id", minWidth: 80 },
    {
      label: "平台",
      prop: "platform",
      minWidth: 80,
      cellRenderer: ({ row, props }: any) => (
        <el-tag
          size={props.size}
          type={platformMap[row.platform]}
          effect="plain"
        >
          {platformOptions.find(item => item.value === row.platform)?.label}
        </el-tag>
      )
    },
    {
      label: "充值状态",
      prop: "recharged",
      minWidth: 100,
      cellRenderer: ({ row, props }: any) => (
        <el-tag
          size={props.size}
          type={rechargedMap[row.recharged]}
          effect="plain"
        >
          {rechargedOptions.find(item => item.value === row.recharged)?.label}
        </el-tag>
      )
    },
    {
      label: "用户名",
      prop: "user.username",
      minWidth: 120,
      cellRenderer: createUsernameRenderer("user.username")
    },
    {
      label: "订单ID",
      prop: "order_id",
      minWidth: 100,
      cellRenderer: ({ row }: any) => {
        const handleClick = () => {
          router.push({ path: "/order", query: { id: row.order_id } });
        };
        return (
          <span class="cursor-pointer" onClick={handleClick}>
            {row.order_id}
          </span>
        );
      }
    },
    { label: "交易单号", prop: "tid", minWidth: 150 },
    { label: "产品代码", prop: "product_code", minWidth: 120 },
    { label: "周期", prop: "period", minWidth: 50 },
    { label: "数量", prop: "count", minWidth: 50 },
    { label: "价格", prop: "price", minWidth: 80 },
    { label: "实付金额", prop: "amount", minWidth: 80 },
    {
      label: "时间戳",
      prop: "timestamp",
      minWidth: 180,
      formatter: ({ timestamp }: any) =>
        timestamp ? dayjs(timestamp * 1000).format("YYYY-MM-DD HH:mm:ss") : "-"
    },
    {
      label: "创建时间",
      prop: "created_at",
      minWidth: 180,
      formatter: ({ created_at }: any) =>
        created_at ? dayjs(created_at).format("YYYY-MM-DD HH:mm:ss") : "-"
    },
    { label: "操作", fixed: "right", slot: "operation", width: 110 }
  ];

  const handleRowClick = (row: any, _column: any, event: any) => {
    const target = event.target as HTMLElement;
    if (
      target.tagName === "BUTTON" ||
      target.closest("button") ||
      target.closest(".el-button")
    ) {
      return;
    }
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
};
