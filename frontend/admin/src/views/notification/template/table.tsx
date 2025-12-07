import { ref } from "vue";
import { channelOptions } from "./dictionary";

const channelMap = channelOptions.reduce<Record<string, string>>((acc, cur) => {
  acc[cur.value] = cur.label;
  return acc;
}, {});

export function useNotificationTemplateTable() {
  const tableRef = ref();

  const tableColumns: TableColumnList = [
    {
      label: "ID",
      prop: "id",
      minWidth: 90
    },
    {
      label: "名称",
      prop: "name",
      minWidth: 180
    },
    {
      label: "标识",
      prop: "code",
      minWidth: 180
    },
    {
      label: "通道",
      prop: "channels",
      minWidth: 200,
      cellRenderer: ({ row }) => {
        if (!row.channels || row.channels.length === 0) {
          return <span class="text-muted">-</span>;
        }
        return (
          <div>
            {row.channels.map((item: string) => (
              <el-tag key={item} size="small" type="info" class="mr-1 mb-1">
                {channelMap[item] ?? item}
              </el-tag>
            ))}
          </div>
        );
      }
    },
    {
      label: "变量",
      prop: "variables",
      minWidth: 200,
      cellRenderer: ({ row }) => {
        if (!row.variables || row.variables.length === 0) {
          return <span class="text-muted">-</span>;
        }
        return (
          <div>
            {row.variables.map((item: string) => (
              <el-tag key={item} size="small" effect="light" class="mr-1 mb-1">
                {item}
              </el-tag>
            ))}
          </div>
        );
      }
    },
    {
      label: "状态",
      prop: "status",
      width: 100,
      cellRenderer: ({ row, props }) => (
        <el-tag
          size={props.size}
          type={row.status === 1 ? "success" : "info"}
          effect="plain"
        >
          {row.status === 1 ? "启用" : "停用"}
        </el-tag>
      )
    },
    {
      label: "更新时间",
      prop: "updated_at",
      minWidth: 180
    },
    {
      label: "操作",
      prop: "operation",
      width: 110,
      fixed: "right",
      slot: "operation"
    }
  ];

  return {
    tableRef,
    tableColumns
  };
}
