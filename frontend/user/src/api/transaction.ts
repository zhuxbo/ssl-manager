import { http } from "@/utils/http";

export interface IndexParams {
  currentPage?: number;
  pageSize?: number;
  quickSearch?: string;
  username?: string;
  type?: number;
  transaction_id?: string;
  amount?: number[];
  created_at?: [string, string];
}

/** 获取交易记录列表 */
export function index(params: IndexParams): Promise<BaseResponse> {
  return http.get<BaseResponse<null>, IndexParams>("/transaction", { params });
}
