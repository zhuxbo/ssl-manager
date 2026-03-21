import { http } from "@/utils/http";

export interface Acme {
  id: number;
  user_id: number;
  product_id: number;
  brand: string;
  period: number;
  purchased_standard_count: number;
  purchased_wildcard_count: number;
  refer_id: string | null;
  api_id: string | null;
  vendor_id: string | null;
  eab_kid: string | null;
  eab_hmac: string | null;
  period_from: string | null;
  period_till: string | null;
  cancelled_at: string | null;
  status: string;
  remark: string | null;
  amount: string;
  admin_remark: string | null;
  created_at: string;
  updated_at: string;
  user?: { id: number; username: string };
  product?: { id: number; name: string };
}

export interface AcmeParams {
  currentPage?: number;
  pageSize?: number;
  user_id?: number;
  brand?: string;
  status?: string;
}

/** 创建 ACME 订阅 */
export function createOrder(data: {
  user_id: number;
  product_id: number;
  period: number;
  purchased_standard_count?: number;
  purchased_wildcard_count?: number;
}): Promise<BaseResponse> {
  return http.post<BaseResponse<null>, typeof data>("/acme/new", { data });
}

/** 支付 ACME 订阅 */
export function payOrder(id: number): Promise<BaseResponse> {
  return http.post<BaseResponse<null>, null>(`/acme/pay/${id}`);
}

/** 提交 ACME 订阅 */
export function commitOrder(id: number): Promise<BaseResponse> {
  return http.post<BaseResponse<null>, null>(`/acme/commit/${id}`);
}

/** 获取 ACME 列表 */
export function getAcmes(params: AcmeParams): Promise<BaseResponse> {
  return http.get<BaseResponse, AcmeParams>("/acme", { params });
}

/** 获取 ACME 详情 */
export function getAcmeDetail(id: number): Promise<BaseResponse> {
  return http.get<BaseResponse<null>, null>(`/acme/${id}`);
}

/** 同步 ACME */
export function syncAcme(id: number): Promise<BaseResponse> {
  return http.post<BaseResponse<null>, null>(`/acme/sync/${id}`);
}

/** 取消 ACME */
export function cancelAcme(id: number): Promise<BaseResponse> {
  return http.post<BaseResponse<null>, null>(`/acme/commit-cancel/${id}`);
}

/** ACME 备注 */
export function remarkAcme(id: number, remark: string): Promise<BaseResponse> {
  return http.post<BaseResponse<null>, { remark: string }>(
    `/acme/remark/${id}`,
    { data: { remark } }
  );
}
