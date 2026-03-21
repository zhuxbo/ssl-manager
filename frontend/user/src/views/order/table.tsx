import { ref } from "vue";
import dayjs from "dayjs";
import { DocumentCopy } from "@element-plus/icons-vue";
import { message } from "@shared/utils";
import { action, actionType, status, statusType } from "./dictionary";
import { periodLabels } from "@/views/system/dictionary";

const expiryColor = (date: string | null) => {
  if (!date) return "";
  const diff = dayjs(date).diff(dayjs(), "day");
  if (diff < 0) return "color: var(--el-color-danger)";
  if (diff < 15) return "color: var(--el-color-warning)";
  return "";
};

// 获取委托验证前缀
const getDelegationPrefix = (ca?: string) => {
  const caLower = (ca || "").toLowerCase();
  switch (caLower) {
    case "sectigo":
    case "comodo":
      return "_pki-validation";
    case "certum":
      return "_certum";
    case "digicert":
    case "geotrust":
    case "thawte":
    case "rapidssl":
    case "symantec":
    case "trustasia":
      return "_dnsauth";
    default:
      return "_acme-challenge";
  }
};

export function useOrderTable() {
  const tableRef = ref();
  const selectedIds = ref([]);
  const selectedRows = ref([]);

  const handleSelectionChange = val => {
    selectedIds.value = val.map(v => v.id);
    selectedRows.value = val;
    // 每次选择后重置表格高度
    tableRef.value.setAdaptive();
  };

  const handleCancelSelection = () => {
    selectedIds.value = [];
    tableRef.value.getTableRef().clearSelection();
  };

  const copyDnsRecords = (row: any) => {
    const cert = row.latest_cert;
    const dcv = cert.dcv;

    // 委托验证
    if (dcv?.is_delegate) {
      const prefix = getDelegationPrefix(dcv.ca || row.product?.ca);
      const validation = cert.validation || [];
      // 按 delegation_id 去重
      const seen = new Map();
      const uniqueDelegations = validation.filter((item: any) => {
        if (!item.delegation_id) return false;
        if (seen.has(item.delegation_id)) return false;
        seen.set(item.delegation_id, true);
        return true;
      });

      // 添加空检查
      if (uniqueDelegations.length === 0) {
        message("暂无可复制的委托验证记录", { type: "warning" });
        return;
      }

      const records = uniqueDelegations.map((item: any) => {
        const zone =
          item.delegation_zone || (item.domain || "").replace(/^\*\./, "");
        return `域名：${zone}\n主机记录：${prefix}\n解析类型：CNAME\n记录值：${item.delegation_target}`;
      });

      navigator.clipboard.writeText(records.join("\n\n")).then(() => {
        message("解析记录已复制到剪贴板", { type: "success" });
      });
      return;
    }

    // 普通 DNS 验证
    const domain = cert.common_name;
    const host = dcv.dns.host;
    const type = dcv.dns.type;
    const value = dcv.dns.value;

    const record = `域名：${domain}\n主机记录：${host}\n解析类型：${type}\n记录值：${value}`;

    navigator.clipboard.writeText(record).then(() => {
      message("解析记录已复制到剪贴板", { type: "success" });
    });
  };

  const tableColumns: TableColumnList = [
    {
      label: "勾选列",
      type: "selection",
      reserveSelection: true
    },
    {
      label: "通用名称",
      prop: "latest_cert.common_name",
      minWidth: 200,
      cellRenderer: ({ row }) => {
        const productName = row.product?.name || "-";
        const commonName = row.latest_cert?.common_name;
        const dcv = row.latest_cert?.dcv;
        const validation = row.latest_cert?.validation;
        const shouldShowCopyButton =
          ["unpaid", "pending", "processing"].includes(
            row.latest_cert?.status
          ) &&
          dcv?.method &&
          (dcv?.is_delegate
            ? validation && validation.some((item: any) => item.delegation_id)
            : ["cname", "txt"].includes(dcv.method) && dcv?.dns?.value);

        return (
          <div class="flex flex-col">
            <div class="flex items-center gap-1">
              <span>{commonName || "-"}</span>
              {shouldShowCopyButton && (
                <el-button
                  link
                  size="small"
                  onClick={(e: { stopPropagation: () => void }) => {
                    e.stopPropagation();
                    copyDnsRecords(row);
                  }}
                  class="p-0! m-0! bg-transparent! border-none! shadow-none! text-gray-500 hover:text-blue-500"
                >
                  <el-icon size="14">
                    <DocumentCopy />
                  </el-icon>
                </el-button>
              )}
            </div>
            <span class="text-xs text-gray-400">{productName}</span>
          </div>
        );
      }
    },
    {
      label: "周期",
      prop: "period",
      minWidth: 100,
      cellRenderer: ({ row }) => (
        <div class="flex flex-col">
          <span>{periodLabels[row.period] || "-"}</span>
          <span class="text-xs text-gray-400">¥{row.amount}</span>
        </div>
      )
    },
    {
      label: "操作",
      prop: "latest_cert.action",
      minWidth: 80,
      cellRenderer: ({ row }) => (
        <el-tag type={actionType[row.latest_cert.action]}>
          {action[row.latest_cert.action]}
        </el-tag>
      )
    },
    {
      label: "状态",
      prop: "latest_cert.status",
      minWidth: 80,
      cellRenderer: ({ row }) => (
        <el-tag type={statusType[row.latest_cert.status] || "info"}>
          {status[row.latest_cert.status]}
        </el-tag>
      )
    },
    {
      label: "订单周期",
      prop: "period_till",
      minWidth: 170,
      sortable: "custom",
      cellRenderer: ({ row }) => {
        const from = row.period_from
          ? dayjs(row.period_from).format("YYYY-MM-DD HH:mm:ss")
          : row.created_at
            ? dayjs(row.created_at).format("YYYY-MM-DD HH:mm:ss")
            : "-";
        const till = row.period_till
          ? dayjs(row.period_till).format("YYYY-MM-DD HH:mm:ss")
          : "-";
        return (
          <div class="flex flex-col">
            <span style={expiryColor(row.period_till)}>{till}</span>
            <span class="text-xs text-gray-400">{from}</span>
          </div>
        );
      }
    },
    {
      label: "证书周期",
      prop: "expires_at",
      minWidth: 170,
      sortable: "custom",
      cellRenderer: ({ row }) => {
        const issued = row.latest_cert?.issued_at
          ? dayjs(row.latest_cert.issued_at).format("YYYY-MM-DD HH:mm:ss")
          : "-";
        const expires = row.latest_cert?.expires_at
          ? dayjs(row.latest_cert.expires_at).format("YYYY-MM-DD HH:mm:ss")
          : "-";
        return (
          <div class="flex flex-col">
            <span style={expiryColor(row.latest_cert?.expires_at)}>
              {expires}
            </span>
            <span class="text-xs text-gray-400">{issued}</span>
          </div>
        );
      }
    },
    {
      label: "操作",
      fixed: "right",
      width: 180,
      slot: "operation"
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
    selectedRows,
    tableColumns,
    handleSelectionChange,
    handleCancelSelection,
    handleRowClick
  };
}
