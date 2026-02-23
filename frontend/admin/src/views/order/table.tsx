import { ref } from "vue";
import dayjs from "dayjs";
import { DocumentCopy } from "@element-plus/icons-vue";
import { message } from "@shared/utils";
import {
  channel,
  channelType,
  action,
  actionType,
  status,
  statusType
} from "./dictionary";
import { periodLabels } from "@/views/system/dictionary";
import { createUsernameRenderer } from "@/views/system/username";

// 获取委托验证前缀
const getDelegationPrefix = (ca?: string, channel?: string) => {
  if (channel === "acme") return "_acme-challenge";
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
      const prefix = getDelegationPrefix(dcv.ca || row.product?.ca, cert.channel);
      const validation = cert.validation || [];
      // 按 delegation_id 去重
      const seen = new Map();
      const uniqueDelegations = validation.filter((item: any) => {
        if (!item.delegation_id) return false;
        if (seen.has(item.delegation_id)) return false;
        seen.set(item.delegation_id, true);
        return true;
      });

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
      label: "ID",
      prop: "id",
      minWidth: 120
    },
    {
      label: "用户名",
      prop: "user.username",
      minWidth: 120,
      cellRenderer: createUsernameRenderer("user.username")
    },
    {
      label: "产品",
      prop: "product.name",
      minWidth: 150
    },
    {
      label: "通用名称",
      prop: "latest_cert.common_name",
      minWidth: 150,
      cellRenderer: ({ row }) => {
        const commonName = row.latest_cert?.common_name;
        const dcv = row.latest_cert?.dcv;
        const shouldShowCopyButton =
          ["unpaid", "pending", "processing"].includes(
            row.latest_cert?.status
          ) &&
          dcv?.method &&
          (dcv?.is_delegate ||
            (["cname", "txt"].includes(dcv.method) && dcv?.dns?.value));
        return (
          <div className="flex items-center gap-1">
            <span>{commonName || "-"}</span>
            {shouldShowCopyButton && (
              <el-button
                link
                size="small"
                onClick={(e: { stopPropagation: () => void }) => {
                  e.stopPropagation();
                  copyDnsRecords(row);
                }}
                className="!p-0 !m-0 !mt-1 !bg-transparent !border-none !shadow-none align-middle text-gray-500 hover:text-blue-500"
              >
                <el-icon size="14">
                  <DocumentCopy />
                </el-icon>
              </el-button>
            )}
          </div>
        );
      }
    },
    {
      label: "有效期",
      prop: "period",
      minWidth: 120,
      formatter: ({ period }) => {
        return periodLabels[period];
      }
    },
    {
      label: "金额",
      prop: "amount",
      minWidth: 80
    },
    {
      label: "渠道",
      prop: "latest_cert.channel",
      minWidth: 80,
      cellRenderer: ({ row }) => {
        return (
          <el-tag type={channelType[row.latest_cert.channel]}>
            {channel[row.latest_cert.channel]}
          </el-tag>
        );
      }
    },
    {
      label: "操作",
      prop: "latest_cert.action",
      minWidth: 80,
      cellRenderer: ({ row }) => {
        return (
          <el-tag type={actionType[row.latest_cert.action]}>
            {action[row.latest_cert.action]}
          </el-tag>
        );
      }
    },
    {
      label: "状态",
      prop: "latest_cert.status",
      minWidth: 80,
      cellRenderer: ({ row }) => {
        return (
          <el-tag type={statusType[row.latest_cert.status] || "info"}>
            {status[row.latest_cert.status]}
          </el-tag>
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
      width: 140,
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
