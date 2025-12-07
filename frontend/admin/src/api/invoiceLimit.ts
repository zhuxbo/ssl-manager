import { http } from "@/utils/http";

export interface IndexParams {
  currentPage?: number;
  pageSize?: number;
  quickSearch?: string;
  username?: string;
  type?: string;
  limit_id?: string;
  amount?: number[];
  created_at?: [string, string];
}

/** 获取发票额度列表 */
export function index(params: IndexParams): Promise<BaseResponse> {
  return http.get<BaseResponse<null>, IndexParams>("/invoice-limit", {
    params
  });
}
