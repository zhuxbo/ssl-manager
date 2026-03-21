import type { TableColumns } from "@pureadmin/table";

declare global {
  type TableColumnList = Array<TableColumns>;
}

export {};
