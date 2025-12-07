import { http } from "@/utils/http";

export interface IndexParams {
  currentPage?: number;
  pageSize?: number;
  domain?: string;
  issued_at?: [string, string];
  expires_at?: [string, string];
  status?: number;
  order_id?: number;
}

/** 获取证书列表 */
export function index(params: IndexParams): Promise<BaseResponse> {
  return http.get<BaseResponse<null>, IndexParams>("/cert", { params });
}

/** 获取证书详情 */
export function show(id: number): Promise<BaseResponse> {
  return http.get<BaseResponse<null>, { id: number }>(`/cert/${id}`);
}

/** 批量获取证书 */
export function batchShow(ids: number[]): Promise<BaseResponse> {
  return http.get<BaseResponse<null>, { ids: number[] }>(`/cert/batch`, {
    params: { ids }
  });
}
