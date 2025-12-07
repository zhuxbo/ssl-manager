import { http } from "@/utils/http";

export interface IndexParams {
  currentPage?: number;
  pageSize?: number;
  quickSearch?: string;
  platform?: string;
  tid?: string;
  username?: string;
  type?: number;
  recharged?: number;
  created_at?: [string, string];
}

export interface AgisoDetail {
  id: number;
  platform: string;
  sign: string;
  data: string;
  tid: string;
  type: number;
  price: string;
  count: number;
  amount: string;
  user_id: number;
  order_id: number;
  recharged: number;
  timestamp: number;
  created_at: string;
  user?: {
    id: number;
    username: string;
    email: string;
  };
}

/** 获取阿奇索记录列表 */
export function index(params: IndexParams): Promise<BaseResponse> {
  return http.get<BaseResponse<null>, IndexParams>("/agiso", { params });
}

/** 获取阿奇索记录详情 */
export function show(id: number): Promise<BaseResponse<AgisoDetail>> {
  return http.get(`/agiso/${id}`);
}

/** 删除阿奇索记录 */
export function destroy(id: number): Promise<BaseResponse> {
  return http.delete(`/agiso/${id}`);
}

/** 批量删除阿奇索记录 */
export function batchDestroy(ids: number[]): Promise<BaseResponse> {
  return http.delete("/agiso", { data: { ids } });
}
